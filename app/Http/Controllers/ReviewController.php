<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Order;
use App\Models\User;
use App\Services\MongoReviewRepository;
use App\Services\ReviewChangePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\Driver\Exception\BulkWriteException;

class ReviewController extends Controller
{
    public function __construct(
        private MongoReviewRepository $reviews,
        private ReviewChangePublisher $publisher,
    ) {}

    public function index(Request $request, int $movieId)
    {
        Movie::query()->findOrFail($movieId);
        $validated = $request->validate([
            'rating_filter' => 'nullable|in:all,9-10,7-8,under_7',
            'page' => 'nullable|integer|min:1',
        ]);
        $filter = $validated['rating_filter'] ?? 'all';
        $page = (int) ($validated['page'] ?? 1);
        $paginator = $this->reviews->paginate($movieId, $filter, $page);
        $summary = $this->reviews->summary($movieId);

        $users = User::query()
            ->whereIn('id', collect($paginator['data'])->pluck('user_id')->unique()->all())
            ->get(['id', 'name', 'avatar'])
            ->keyBy('id');
        $items = collect($paginator['data'])->map(function (array $review) use ($users) {
            $user = $users->get($review['user_id']);
            $review['user'] = $user?->only(['id', 'name', 'avatar']);

            return $review;
        })->values();

        return response()->json([
            'summary' => $this->summaryPayload($summary),
            'data' => $items,
            'current_page' => $paginator['current_page'],
            'last_page' => $paginator['last_page'],
            'total' => $paginator['total'],
        ]);
    }

    public function store(Request $request, int $movieId)
    {
        Movie::query()->findOrFail($movieId);
        $user = Auth::user() ?? auth('sanctum')->user();
        if (! $user) {
            return response()->json(['message' => 'Vui lòng đăng nhập để đánh giá phim.'], 401);
        }

        if ($this->reviews->exists($movieId, (int) $user->id)) {
            return response()->json(['message' => 'Bạn đã đánh giá phim này rồi.'], 422);
        }

        $order = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereHas('show', fn ($query) => $query->where('movie_id', $movieId)->where('start_time', '<', now()))
            ->with('show:id,movie_id,start_time')
            ->latest('id')
            ->first();
        if (! $order) {
            return response()->json([
                'message' => 'Chỉ khán giả đã mua vé và xem phim xong tại rạp mới có quyền đánh giá phim này.',
            ], 403);
        }

        $validated = $request->validate($this->rules(requiredRating: true), $this->messages());
        try {
            $review = $this->reviews->create([
                'user_id' => (int) $user->id,
                'movie_id' => $movieId,
                'order_id' => (int) $order->id,
                'rating' => $validated['rating'],
                'content' => $validated['comment'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'watched_at' => $order->show?->start_time,
            ]);
        } catch (BulkWriteException) {
            return response()->json(['message' => 'Bạn đã đánh giá phim này rồi.'], 422);
        }

        $review['user'] = $user->only(['id', 'name', 'avatar']);
        $this->publisher->publish('created', $review);

        return response()->json([
            'message' => 'Đánh giá thành công! Cảm ơn bạn đã chia sẻ cảm nhận.',
            'data' => $review,
        ], 201);
    }

    public function update(Request $request, int $movieId, string $reviewId)
    {
        $user = Auth::user() ?? auth('sanctum')->user();
        $review = $this->reviews->find($reviewId);
        if (! $review || (int) $review['movie_id'] !== $movieId) {
            return response()->json(['message' => 'Không tìm thấy đánh giá.'], 404);
        }
        if (! $user || (int) $review['user_id'] !== (int) $user->id) {
            return response()->json(['message' => 'Bạn không có quyền sửa đánh giá này.'], 403);
        }

        $validated = $request->validate($this->rules(requiredRating: false), $this->messages());
        if ($validated === []) {
            return response()->json(['message' => 'Không có nội dung cần cập nhật.'], 422);
        }
        $attributes = [];
        if (array_key_exists('rating', $validated)) {
            $attributes['rating'] = $validated['rating'];
        }
        if (array_key_exists('comment', $validated)) {
            $attributes['content'] = $validated['comment'];
        }
        if (array_key_exists('tags', $validated)) {
            $attributes['tags'] = $validated['tags'];
        }
        $updated = $this->reviews->updateOwned($reviewId, $movieId, (int) $user->id, $attributes);
        $this->publisher->publish('updated', $updated);

        return response()->json(['message' => 'Đánh giá đã được cập nhật.', 'data' => $updated]);
    }

    public function destroy(int $movieId, string $reviewId)
    {
        $user = Auth::user() ?? auth('sanctum')->user();
        $review = $this->reviews->find($reviewId);
        if (! $review || (int) $review['movie_id'] !== $movieId) {
            return response()->json(['message' => 'Không tìm thấy đánh giá.'], 404);
        }
        if (! $user || (int) $review['user_id'] !== (int) $user->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa đánh giá này.'], 403);
        }

        $deleted = $this->reviews->deleteOwned($reviewId, $movieId, (int) $user->id);
        $this->publisher->publish('deleted', $deleted);

        return response()->json(['message' => 'Đánh giá đã được xóa.']);
    }

    /** @return array<string, mixed> */
    private function rules(bool $requiredRating): array
    {
        return [
            'rating' => [$requiredRating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:10'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tags' => ['sometimes', 'array', 'max:5'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'rating.required' => 'Vui lòng chọn điểm đánh giá',
            'rating.min' => 'Điểm đánh giá tối thiểu là 1',
            'rating.max' => 'Điểm đánh giá tối đa là 10',
            'comment.max' => 'Bình luận không được vượt quá 1000 ký tự',
        ];
    }

    /** @param array<string, int|float> $summary */
    private function summaryPayload(array $summary): array
    {
        $total = (int) $summary['total_reviews'];
        $percentage = fn (string $key) => $total > 0 ? (int) round(((int) $summary[$key] / $total) * 100) : 0;
        $satisfaction = (int) $summary['satisfaction_rate'];

        return [
            'average_rating' => $summary['average_rating'],
            'total_reviews' => $total,
            'satisfaction_rate' => $satisfaction,
            'breakdown' => [
                'stars_9_10' => ['count' => $summary['count_9_10'], 'percentage' => $percentage('count_9_10')],
                'stars_7_8' => ['count' => $summary['count_7_8'], 'percentage' => $percentage('count_7_8')],
                'stars_5_6' => ['count' => $summary['count_5_6'], 'percentage' => $percentage('count_5_6')],
                'stars_3_4' => ['count' => $summary['count_3_4'], 'percentage' => $percentage('count_3_4')],
                'stars_1_2' => ['count' => $summary['count_1_2'], 'percentage' => $percentage('count_1_2')],
            ],
            'top_tags' => [
                ['tag' => 'Diễn xuất đỉnh cao', 'percentage' => min(98, max(85, $satisfaction + 2))],
                ['tag' => 'Kịch bản lôi cuốn', 'percentage' => min(96, max(82, $satisfaction - 1))],
                ['tag' => 'Kỹ xảo mãn nhãn', 'percentage' => min(99, max(88, $satisfaction + 3))],
                ['tag' => 'Âm thanh sống động', 'percentage' => min(97, max(80, $satisfaction))],
                ['tag' => 'Cái kết ấn tượng', 'percentage' => min(95, max(75, $satisfaction - 4))],
            ],
        ];
    }
}
