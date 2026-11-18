<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\OrderSeat;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Show;
use App\Services\RedisQueryCache;
use App\Services\RedisSeatHoldService;
use App\Services\ShowSchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private RedisQueryCache $cache,
        private RedisSeatHoldService $seatHolds,
    ) {}

    /**
     * [Public] Lấy danh sách suất chiếu theo phim và ngày.
     */
    public function publicIndex(Request $request)
    {
        $filters = $request->validate([
            'movie_id' => 'nullable|integer|exists:movies,id',
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $movieId = isset($filters['movie_id']) ? (int) $filters['movie_id'] : null;
        $date = $filters['date'] ?? null;
        $cacheKey = hash('sha256', json_encode([$movieId, $date], JSON_THROW_ON_ERROR));

        $cached = $this->cache->remember('shows:list', $cacheKey, (int) config('nosql.redis.show_ttl_seconds', 300), function () use ($movieId, $date) {
            $query = Show::with(['movie', 'room']);

            if ($movieId !== null) {
                $query->where('movie_id', $movieId);
            }

            if ($date !== null) {
                $query->whereDate('start_time', $date);
            } else {
                $query->where('start_time', '>=', now());
            }

            return $query->orderBy('start_time')->orderBy('id')->get()->toArray();
        });

        return response()->json(['data' => $cached['value']])->header('X-Cache', strtoupper($cached['status']));
    }

    /**
     * [Public] Lấy sơ đồ ghế và trạng thái cho một suất chiếu.
     */
    public function getSeats(int $id)
    {
        $show = Show::with(['room', 'movie'])->findOrFail($id);
        $seats = Seat::where('room_id', $show->room_id)->orderBy('row_label')->orderBy('seat_number')->get();
        $currentUserId = auth('sanctum')->id() ?? auth()->id();
        $redisOwners = $this->seatHolds->owners($id, $seats->pluck('id')->map(fn ($seatId): int => (int) $seatId)->all());
        $currentOwner = $currentUserId ? $this->seatHolds->ownerForUser((int) $currentUserId) : null;

        // Lấy danh sách ghế đang giữ của chính user này cho suất chiếu này
        $userHoldingSeatIds = [];
        if ($currentUserId) {
            $userHoldingSeatIds = OrderSeat::whereHas('order', function ($q) use ($id, $currentUserId) {
                $q->where('show_id', $id)
                    ->where('user_id', $currentUserId)
                    ->where('status', 'pending')
                    ->where('hold_expires_at', '>', now());
            })->pluck('seat_id')->toArray();
        }

        // Lấy danh sách ghế đã bị đặt hoặc người khác đang giữ
        $unavailableSeatIds = OrderSeat::whereHas('order', function ($q) use ($id, $currentUserId) {
            $q->where('show_id', $id)
                ->where(function ($query) use ($currentUserId) {
                    $query->whereIn('status', ['paid', 'completed'])
                        ->orWhere(function ($sub) use ($currentUserId) {
                            $sub->where('status', 'pending')
                                ->where('hold_expires_at', '>', now());
                            if ($currentUserId) {
                                $sub->where('user_id', '!=', $currentUserId);
                            }
                        });
                });
        })->pluck('seat_id')->toArray();

        foreach ($redisOwners as $seatId => $owner) {
            if ($currentOwner !== null && $owner === $currentOwner) {
                $userHoldingSeatIds[] = $seatId;
            } else {
                $unavailableSeatIds[] = $seatId;
            }
        }
        $userHoldingSeatIds = array_values(array_unique(array_map('intval', $userHoldingSeatIds)));
        $unavailableSeatIds = array_values(array_unique(array_map('intval', $unavailableSeatIds)));

        $seatsData = $seats->map(function ($seat) use ($unavailableSeatIds, $userHoldingSeatIds) {
            $status = 'available';
            if (in_array($seat->id, $unavailableSeatIds)) {
                $status = 'booked';
            }

            return [
                'id' => $seat->id,
                'row_label' => $seat->row_label,
                'seat_number' => $seat->seat_number,
                'type' => $seat->type,
                'status' => $status,
                'is_current_user_held' => in_array($seat->id, $userHoldingSeatIds),
            ];
        });

        return response()->json([
            'data' => [
                'show' => $show,
                'seats' => $seatsData,
                'user_held_seat_ids' => $userHoldingSeatIds,
            ],
        ]);
    }

    /**
     * [Admin] Danh sách tất cả suất chiếu.
     */
    public function index()
    {
        $shows = Show::with(['movie', 'room'])
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json(['data' => $shows]);
    }

    public function adminPage(Request $request): View
    {
        $validator = Validator::make($request->query(), [
            'period' => 'sometimes|required|in:today,upcoming,all,custom',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'movie_id' => 'nullable|integer|exists:movies,id',
            'from' => 'exclude_unless:period,custom|required_without:to|nullable|date_format:Y-m-d\TH:i',
            'to' => 'exclude_unless:period,custom|required_without:from|nullable|date_format:Y-m-d\TH:i'
                .($request->filled(['from', 'to']) ? '|after_or_equal:from' : ''),
        ], [
            'period.*' => 'Khoảng thời gian không hợp lệ.',
            'room_id.*' => 'Vui lòng chọn phòng chiếu hợp lệ.',
            'movie_id.*' => 'Vui lòng chọn phim hợp lệ.',
            'from.required_without' => 'Nhập mốc bắt đầu hoặc mốc kết thúc để lọc theo thời gian tùy chọn.',
            'to.required_without' => 'Nhập mốc bắt đầu hoặc mốc kết thúc để lọc theo thời gian tùy chọn.',
            'from.date_format' => 'Mốc bắt đầu phải gồm ngày và giờ hợp lệ.',
            'to.date_format' => 'Mốc kết thúc phải gồm ngày và giờ hợp lệ.',
            'to.after_or_equal' => 'Mốc kết thúc phải bằng hoặc sau mốc bắt đầu.',
        ]);

        $shows = null;
        if ($validator->passes()) {
            $filters = $validator->validated();
            $period = $filters['period'] ?? 'today';
            $timezone = ShowSchedulingService::TIMEZONE;
            $storageTimezone = config('app.timezone');
            $query = Show::with(['movie:id,title', 'room:id,name']);

            foreach (['room_id', 'movie_id'] as $field) {
                if (isset($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }

            if ($period === 'today') {
                $today = CarbonImmutable::now($timezone)->startOfDay();
                $query->where('start_time', '>=', $today->setTimezone($storageTimezone))
                    ->where('start_time', '<', $today->addDay()->setTimezone($storageTimezone));
            } elseif ($period === 'upcoming') {
                $query->where('start_time', '>=', now());
            } elseif ($period === 'custom') {
                if (isset($filters['from'])) {
                    $from = CarbonImmutable::parse($filters['from'], $timezone)->setTimezone($storageTimezone);
                    $query->where('start_time', '>=', $from);
                }
                if (isset($filters['to'])) {
                    $until = CarbonImmutable::parse($filters['to'], $timezone)->addMinute()->setTimezone($storageTimezone);
                    $query->where('start_time', '<', $until);
                }
            }

            $direction = $period === 'all' ? 'desc' : 'asc';
            $shows = $query->orderBy('start_time', $direction)->orderBy('id', $direction)
                ->paginate(20)->appends($filters);
        }

        return view('admin.shows.index', [
            'shows' => $shows,
            'rooms' => Room::orderBy('id')->get(['id', 'name']),
            'movies' => Movie::orderBy('title')->get(['id', 'title']),
            'inputs' => array_filter($request->only(['period', 'room_id', 'movie_id', 'from', 'to']), 'is_string'),
            'filterErrors' => $validator->errors()->unique(),
        ]);
    }

    public function store(Request $request, ShowSchedulingService $scheduling): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'room_id' => 'required|exists:rooms,id',
            'start_time' => 'required|date',
            'price' => 'required|numeric|min:0',
        ]);

        $show = $scheduling->save($validated)->load(['movie', 'room']);
        $this->cache->invalidate(['shows:list']);

        return response()->json(['data' => $show, 'message' => 'Tạo suất chiếu thành công!'], 201);
    }

    public function update(Request $request, ShowSchedulingService $scheduling, int $id): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => 'sometimes|exists:movies,id',
            'room_id' => 'sometimes|exists:rooms,id',
            'start_time' => 'sometimes|date',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $show = $scheduling->save($validated, $id)->load(['movie', 'room']);
        $this->cache->invalidate(['shows:list']);

        return response()->json(['data' => $show, 'message' => 'Cập nhật suất chiếu thành công!']);
    }

    public function getRoomSchedule(Request $request): JsonResponse
    {
        if (! $request->filled(['room_id', 'date'])) {
            return response()->json(['data' => []]);
        }

        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'date' => 'required|date_format:Y-m-d',
            'movie_id' => 'nullable|exists:movies,id',
        ]);

        $timezone = ShowSchedulingService::TIMEZONE;
        $cleanup = ShowSchedulingService::CLEANING_MINUTES;
        $dayStart = CarbonImmutable::parse($validated['date'], $timezone)->startOfDay()
            ->setTimezone(config('app.timezone'));
        $nextDay = $dayStart->addDay();
        $previewEnd = isset($validated['movie_id'])
            ? $nextDay->addMinutes(ShowSchedulingService::duration(Movie::findOrFail($validated['movie_id'])) + $cleanup)
            : $nextDay;

        $shows = Show::with('movie:id,title')
            ->where('room_id', $validated['room_id'])
            ->where('start_time', '<', $previewEnd)
            ->where('end_time', '>', $dayStart->subMinutes($cleanup))
            ->orderBy('start_time')
            ->get()
            ->map(function (Show $show) use ($timezone, $cleanup, $nextDay): array {
                $start = $show->start_time->copy()->setTimezone($timezone);
                $end = $show->end_time->copy()->setTimezone($timezone);
                $cleanEnd = $end->copy()->addMinutes($cleanup);

                return [
                    'id' => $show->id,
                    'movie_title' => $show->movie?->title ?? 'Phim',
                    'duration' => (int) $start->diffInMinutes($end),
                    'start_time' => $start->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'clean_end_time' => $cleanEnd->format('H:i'),
                    'raw_start' => $start->toISOString(),
                    'raw_end' => $end->toISOString(),
                    'raw_clean_end' => $cleanEnd->toISOString(),
                    'in_day' => $start->lt($nextDay),
                ];
            });

        return response()->json(['data' => $shows]);
    }

    /**
     * [Admin] Xóa suất chiếu.
     */
    public function destroy(int $id)
    {
        $show = Show::findOrFail($id);
        $show->delete();
        $this->cache->invalidate(['shows:list']);

        return response()->json(null, 204);
    }
}
