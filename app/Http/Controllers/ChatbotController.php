<?php

namespace App\Http\Controllers;

use App\Events\ActivityOccurred;
use App\Models\Movie;
use App\Models\Show;
use App\Services\MongoChatSessionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(private MongoChatSessionService $sessions) {}

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'session_id' => ['nullable', 'string', 'max:100'],
        ]);
        $sessionId = $validated['session_id'] ?? (string) Str::uuid();
        $userId = auth()->id();

        try {
            $previous = $this->sessions->find($sessionId);
            $session = $this->sessions->append($sessionId, $userId, 'user', $validated['message']);
        } catch (\DomainException $exception) {
            return response()->json(['error' => $exception->getMessage()], 403);
        }

        $movies = Movie::query()
            ->whereIn('status', ['showing', 'coming_soon'])
            ->get(['id', 'title', 'genre', 'age_rating', 'duration_minutes', 'description']);
        $shows = Show::query()
            ->with(['movie:id,title', 'room:id,name'])
            ->whereBetween('start_time', [Carbon::now(), Carbon::now()->addDays(7)])
            ->get(['id', 'movie_id', 'room_id', 'start_time', 'price']);
        $history = collect($previous['messages'] ?? [])->take(-10)->values()->all();
        $contextString = json_encode([
            'available_movies' => $movies,
            'upcoming_shows' => $shows,
            'recent_conversation' => $history,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $systemPrompt = <<<PROMPT
Bạn là trợ lý AI thông minh của rạp chiếu phim Cinema Ticket Management.
Nhiệm vụ của bạn là tư vấn phim, lịch chiếu và giá vé cho khách hàng.
Dữ liệu phim, lịch chiếu và hội thoại gần nhất ở định dạng JSON:
{$contextString}

Quy tắc trả lời:
- Luôn thân thiện, xưng hô bằng "tôi" và gọi khách hàng là "bạn".
- Chỉ trả lời dựa trên dữ liệu được cung cấp. Nếu thông tin không có, hãy nói rõ chưa có thông tin.
- Trả lời bằng văn bản thường, không sử dụng Markdown.
- Cuối câu trả lời phải gợi ý thêm hai câu hỏi, mỗi câu ở dòng mới và bắt đầu bằng [SUGGEST].
PROMPT;

        $apiKey = config('services.gemini.api_key');
        $model = 'gemini-3.6-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $systemPrompt."\n\nCâu hỏi của người dùng: ".$validated['message']]],
                ]],
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'error' => 'API Error: '.$response->body(),
                    'session_id' => $sessionId,
                ], 500);
            }

            $reply = $response->json('candidates.0.content.parts.0.text')
                ?? 'Xin lỗi, tôi không thể trả lời lúc này.';
            $session = $this->sessions->append($sessionId, $userId, 'assistant', $reply);
            ActivityOccurred::dispatch(
                'CHAT_COMPLETED',
                $userId ? (int) $userId : null,
                null,
                null,
                ['session_id' => $sessionId, 'message_count' => count($session['messages'] ?? [])],
                $userId ? 'user' : 'guest',
                'chat_session',
                $sessionId,
            );

            return response()->json([
                'reply' => $reply,
                'session_id' => $sessionId,
                'expires_at' => $session['expires_at'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'session_id' => $sessionId,
            ], 500);
        }
    }
}
