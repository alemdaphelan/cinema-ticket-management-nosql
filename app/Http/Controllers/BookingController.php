<?php

namespace App\Http\Controllers;

use App\Events\ActivityOccurred;
use App\Jobs\ReleaseSeatJob;
use App\Models\Order;
use App\Models\OrderSeat;
use App\Models\Seat;
use App\Models\Show;
use App\Models\Snack;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingHoldService;
use App\Services\RedisMovieLeaderboard;
use App\Services\RedisSeatHoldService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BookingController extends Controller
{
    private const PAYMENT_METHOD_RULE = 'required|in:vnpay,momo';

    public function __construct(
        private BookingHoldService $holds,
        private RedisSeatHoldService $seatHolds,
        private RedisMovieLeaderboard $leaderboard,
    ) {}

    /**
     * Bước 1: Chọn ghế & Giữ ghế (10 phút).
     */
    public function holdSeats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'show_id' => 'required|exists:shows,id',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'required|integer|distinct|exists:seats,id',
            'order_id' => 'nullable|integer',
        ]);

        $show = Show::findOrFail($validated['show_id']);
        $seatIds = array_values(array_map('intval', $validated['seat_ids']));
        sort($seatIds);
        $owner = $this->seatHolds->ownerForUser((int) $request->user()->id);
        $acquiredSeatIds = [];
        $previousSeatIds = [];

        try {
            $result = $this->holds->withUser($request->user()->id, function (User $user) use (
                $validated,
                $show,
                $seatIds,
                $owner,
                &$acquiredSeatIds,
                &$previousSeatIds,
            ): array|JsonResponse {
                if ($this->holds->isBlocked($user)) {
                    return $this->blockedResponse($user);
                }

                $orders = $user->orders()->where('show_id', $show->id)->lockForUpdate();

                if (isset($validated['order_id'])) {
                    $existingOrder = $orders->findOrFail($validated['order_id']);
                    if ($failure = $this->pendingOrderFailure($user, $existingOrder)) {
                        return $failure;
                    }
                } else {
                    $existingOrder = $orders->where('status', 'pending')
                        ->where('hold_expires_at', '>', now())
                        ->latest()
                        ->first();
                }

                $previousSeatIds = $existingOrder
                    ? $existingOrder->seats()->pluck('seat_id')->map(fn ($seatId): int => (int) $seatId)->all()
                    : [];

                $seats = Seat::where('room_id', $show->room_id)
                    ->whereIn('id', $seatIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($seatIds)) {
                    return response()->json(['message' => 'Một hoặc nhiều ghế không thuộc suất chiếu này.'], 409);
                }

                $alreadyBooked = OrderSeat::whereIn('seat_id', $seatIds)
                    ->whereHas('order', function ($query) use ($show, $user) {
                        $query->where('show_id', $show->id)->where(function ($orders) use ($user) {
                            $orders->whereIn('status', ['paid', 'completed'])
                                ->orWhere(function ($pending) use ($user) {
                                    $pending->where('status', 'pending')
                                        ->where('hold_expires_at', '>', now())
                                        ->where('user_id', '!=', $user->id);
                                });
                        });
                    })->exists();

                if ($alreadyBooked) {
                    return response()->json([
                        'message' => 'Một hoặc nhiều ghế bạn chọn vừa có người khác giữ chỗ hoặc đặt mua. Vui lòng chọn ghế khác!',
                    ], 409);
                }

                $ttlSeconds = $existingOrder?->hold_expires_at
                    ? max(1, (int) now()->diffInSeconds($existingOrder->hold_expires_at, false))
                    : (int) config('nosql.redis.seat_hold_ttl_seconds', 600);

                try {
                    $reservation = $this->seatHolds->acquire((int) $show->id, $seatIds, $owner, $ttlSeconds);
                } catch (Throwable $exception) {
                    Log::error('Redis seat hold acquisition failed.', ['exception' => $exception::class]);

                    return response()->json([
                        'code' => 'seat_hold_store_unavailable',
                        'message' => 'Hệ thống giữ ghế đang tạm thời không khả dụng. Vui lòng thử lại.',
                    ], 503);
                }

                if (! $reservation['acquired']) {
                    return response()->json([
                        'code' => 'seat_hold_conflict',
                        'message' => 'Một hoặc nhiều ghế bạn chọn vừa có người khác giữ. Vui lòng chọn ghế khác.',
                        'data' => ['conflict_seat_id' => $reservation['conflict_seat_id']],
                    ], 409);
                }
                $acquiredSeatIds = $seatIds;

                $otherOrders = $user->orders()
                    ->with('seats:id,order_id,seat_id')
                    ->where('status', 'pending')
                    ->when($existingOrder, fn ($orders) => $orders->whereKeyNot($existingOrder->id))
                    ->get(['id', 'show_id']);
                $user->orders()->whereKey($otherOrders->modelKeys())->update([
                    'status' => 'cancelled',
                    'qr_code' => 'reason:change_seats',
                ]);

                $order = $existingOrder ?? $user->orders()->create([
                    'show_id' => $show->id,
                    'status' => 'pending',
                    'hold_expires_at' => now()->addSeconds($ttlSeconds),
                    'total_amount' => 0,
                ]);

                $heldSeats = $order->seats()->pluck('seat_id');
                $order->seats()->whereNotIn('seat_id', $seatIds)->delete();
                foreach ($seats as $seat) {
                    if (! $heldSeats->contains($seat->id)) {
                        $seatPrice = $seat->type === 'vip' ? $show->price * 1.5 : $show->price;
                        $order->seats()->create(['seat_id' => $seat->id, 'price' => $seatPrice]);
                    }
                }

                $this->updateOrderTotal($order, $order->seats()->sum('price') + $order->snacks()->sum('price'));

                return [
                    'order' => $order,
                    'release' => collect($otherOrders)->map(fn (Order $other): array => [
                        'show_id' => (int) $other->show_id,
                        'seat_ids' => $other->seats->pluck('seat_id')->map(fn ($seatId): int => (int) $seatId)->all(),
                    ])->push([
                        'show_id' => (int) $show->id,
                        'seat_ids' => array_values(array_diff($previousSeatIds, $seatIds)),
                    ])->all(),
                ];
            });
        } catch (Throwable $exception) {
            $newlyAcquired = array_values(array_diff($acquiredSeatIds, $previousSeatIds));
            if ($newlyAcquired !== []) {
                try {
                    $this->seatHolds->release((int) $show->id, $newlyAcquired, $owner);
                } catch (Throwable) {
                    // The Redis TTL is the final cleanup guard if rollback cleanup fails.
                }
            }

            throw $exception;
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        foreach ($result['release'] as $release) {
            try {
                $this->seatHolds->release($release['show_id'], $release['seat_ids'], $owner);
            } catch (Throwable $exception) {
                Log::warning('Could not release superseded Redis seat holds.', ['exception' => $exception::class]);
            }
        }

        /** @var Order $order */
        $order = $result['order'];

        if ($order->wasRecentlyCreated) {
            ReleaseSeatJob::dispatch($order->id)->delay($order->hold_expires_at);
        }

        return response()->json([
            'message' => 'Giữ ghế thành công.',
            'data' => [
                'order_id' => $order->id,
                'total_amount' => $order->total_amount,
                'hold_expires_at' => $order->hold_expires_at,
            ],
        ], 201);
    }

    /**
     * Bước 2: Chọn dịch vụ bắp nước.
     */
    public function addSnacks(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'snacks' => 'nullable|array',
            'snacks.*.snack_id' => 'required|integer|distinct|exists:snacks,id',
            'snacks.*.quantity' => 'required|integer|min:1',
            'continue_to_payment' => 'sometimes|boolean',
        ]);

        return $this->withPendingOrder($request, $orderId, function (Order $order) use ($validated): JsonResponse {
            $items = collect($validated['snacks'] ?? [])->keyBy('snack_id');
            $existing = $order->snacks()->get()->keyBy('snack_id');
            $order->snacks()->whereNotIn('snack_id', $items->keys())->delete();

            foreach ($items as $id => $item) {
                $line = $existing->get($id);
                $quantity = (int) $item['quantity'];
                if (! $line) {
                    $snack = Snack::findOrFail($item['snack_id']);
                    $order->snacks()->create([
                        'snack_id' => $id,
                        'quantity' => $quantity,
                        'price' => $snack->price * $quantity,
                    ]);
                } elseif ((int) $line->quantity !== $quantity) {
                    $line->update([
                        'quantity' => $quantity,
                        'price' => $line->price / $line->quantity * $quantity,
                    ]);
                }
            }

            if ($validated['continue_to_payment'] ?? true) {
                $order->checkout_payment_method ??= 'vnpay';
            }

            $seatTotal = $order->seats()->sum('price');
            $snackTotal = $order->snacks()->sum('price');
            $this->updateOrderTotal($order, $seatTotal + $snackTotal);

            return response()->json([
                'message' => 'Thêm bắp nước thành công!',
                'data' => [
                    'total_amount' => $order->fresh()->total_amount,
                    'seat_total' => $seatTotal,
                    'snack_total' => $snackTotal,
                ],
            ]);
        });
    }

    /**
     * Bước 3: Áp dụng Voucher.
     */
    public function applyVoucher(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'voucher_code' => 'required|string',
        ]);

        return $this->withPendingOrder($request, $orderId, function (Order $order) use ($validated): JsonResponse {
            $voucher = $this->activeVouchers()->where('code', $validated['voucher_code'])->first();

            if (! $voucher) {
                return response()->json(['message' => 'Mã voucher không hợp lệ hoặc đã hết hạn.'], 422);
            }

            if (! $this->hasVoucherUsesRemaining($order, $voucher)) {
                return response()->json(['message' => 'Bạn đã sử dụng hết số lần cho phép của voucher này.'], 422);
            }

            // Tính tổng trước giảm
            $seatTotal = $order->seats()->sum('price');
            $snackTotal = $order->snacks()->sum('price');
            $subtotal = $seatTotal + $snackTotal;

            if ($subtotal < $voucher->min_order_value) {
                return response()->json([
                    'message' => 'Đơn hàng chưa đạt giá trị tối thiểu '.number_format($voucher->min_order_value).'đ để áp dụng voucher.',
                ], 422);
            }

            $discount = $this->voucherDiscount($voucher, $subtotal);

            $finalTotal = max(0, $subtotal - $discount);

            $order->update([
                'voucher_id' => $voucher->id,
                'total_amount' => $finalTotal,
            ]);

            return response()->json([
                'message' => 'Áp dụng voucher thành công!',
                'data' => [
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total_amount' => $finalTotal,
                    'voucher_code' => $voucher->code,
                ],
            ]);
        });
    }

    private function updateOrderTotal(Order $order, float $subtotal): void
    {
        $voucher = $order->voucher_id ? $this->activeVouchers()->find($order->voucher_id) : null;
        if ($voucher && ($subtotal < $voucher->min_order_value || ! $this->hasVoucherUsesRemaining($order, $voucher))) {
            $voucher = null;
        }

        $order->update([
            'voucher_id' => $voucher?->id,
            'total_amount' => $voucher ? max(0, $subtotal - $this->voucherDiscount($voucher, $subtotal)) : $subtotal,
        ]);
    }

    /** @return Builder<Voucher> */
    private function activeVouchers(): Builder
    {
        return Voucher::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now());
    }

    private function hasVoucherUsesRemaining(Order $order, Voucher $voucher): bool
    {
        return Order::where('user_id', $order->user_id)
            ->where('voucher_id', $voucher->id)
            ->whereIn('status', ['paid', 'completed'])
            ->count() < $voucher->max_uses_per_user;
    }

    private function voucherDiscount(Voucher $voucher, float $subtotal): float
    {
        return $voucher->discount_type === 'percent'
            ? $subtotal * ($voucher->discount_value / 100)
            : (float) $voucher->discount_value;
    }

    public function savePaymentMethod(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate(['payment_method' => self::PAYMENT_METHOD_RULE]);

        return $this->withPendingOrder($request, $orderId, function (Order $order) use ($validated): JsonResponse {
            $order->update(['checkout_payment_method' => $validated['payment_method']]);

            return response()->json(['data' => ['payment_method' => $order->checkout_payment_method]]);
        });
    }

    /**
     * Bước 4: Thanh toán (Mock - giả lập).
     */
    public function pay(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => self::PAYMENT_METHOD_RULE,
        ]);

        return $this->withPendingOrder($request, $orderId, function (Order $order) use ($validated): JsonResponse {
            $qrContent = json_encode([
                'order_id' => $order->id,
                'code' => 'CINEMA-'.strtoupper(Str::random(8)),
            ]);

            $order->update([
                'status' => 'paid',
                'payment_method' => $validated['payment_method'],
                'qr_code' => $qrContent,
            ]);

            $seatIds = $order->seats()->pluck('seat_id')->map(fn ($seatId): int => (int) $seatId)->all();
            $this->releaseSeatHolds($order, $seatIds);
            $movieId = (int) $order->show()->value('movie_id');
            $this->leaderboard->increment(
                $movieId,
                (float) config('nosql.redis.leaderboard_weights.booking', 5) * max(1, count($seatIds)),
            );
            ActivityOccurred::dispatch(
                'ORDER_PAID',
                (int) $order->user_id,
                $movieId,
                (int) $order->id,
                ['payment_method' => $validated['payment_method'], 'seat_count' => count($seatIds)],
                'user',
                'order',
                (string) $order->id,
            );

            return response()->json([
                'message' => 'Thanh toán thành công!',
                'data' => [
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'qr_code' => $qrContent,
                    'total_amount' => $order->total_amount,
                ],
            ]);
        });
    }

    public function cancelOrder(Request $request, int $orderId): JsonResponse
    {
        return $this->holds->withUser($request->user()->id, function (User $user) use ($request, $orderId): JsonResponse {
            $order = $user->orders()->findOrFail($orderId);

            if ($order->cancel_reason === Order::CANCEL_REASON_HOLD_EXPIRED) {
                return $this->pendingOrderFailure($user, $order);
            }

            if ($order->status === 'pending') {
                $seatIds = $order->seats()->pluck('seat_id')->map(fn ($seatId): int => (int) $seatId)->all();
                $order->update([
                    'status' => 'cancelled',
                    'qr_code' => $request->input('reason') === 'change_seats' ? 'reason:change_seats' : null,
                ]);
                $this->releaseSeatHolds($order, $seatIds);
            }

            return response()->json([
                'message' => 'Đã hủy đơn hàng',
                'data' => ['order_id' => $order->id, 'show_id' => $order->show_id],
            ]);
        });
    }

    public function expireHold(Request $request, int $orderId): JsonResponse
    {
        return $this->holds->withUser($request->user()->id, function (User $user) use ($orderId): JsonResponse {
            $order = $user->orders()->findOrFail($orderId);

            if (in_array($order->status, ['paid', 'completed'], true)) {
                return response()->json(['data' => ['status' => $order->status]]);
            }

            if ($failure = $this->pendingOrderFailure($user, $order)) {
                return $failure;
            }

            return response()->json([
                'data' => [
                    'status' => $order->status,
                    'hold_expires_at' => $order->hold_expires_at,
                    'server_time' => now(),
                ],
            ]);
        });
    }

    private function withPendingOrder(Request $request, int $orderId, Closure $action): JsonResponse
    {
        return $this->holds->withUser($request->user()->id, function (User $user) use ($orderId, $action): JsonResponse {
            $order = $user->orders()->lockForUpdate()->findOrFail($orderId);

            return $this->pendingOrderFailure($user, $order) ?? $action($order);
        });
    }

    private function pendingOrderFailure(User $user, Order $order): ?JsonResponse
    {
        if ($this->holds->isBlocked($user)) {
            return $this->blockedResponse($user);
        }

        if ($order->cancel_reason === Order::CANCEL_REASON_HOLD_EXPIRED) {
            return response()->json([
                'code' => 'hold_expired',
                'message' => $this->holds->expirationMessage($user),
                'data' => ['remaining_attempts' => BookingHoldService::EXPIRATION_LIMIT - $user->expired_booking_holds],
            ], 409);
        }

        if ($order->status !== 'pending' || ! $order->hold_expires_at) {
            return response()->json([
                'code' => 'hold_inactive',
                'message' => 'Phiên giữ ghế đã kết thúc. Vui lòng chọn lại ghế.',
            ], 409);
        }

        return null;
    }

    private function blockedResponse(User $user): JsonResponse
    {
        return response()->json([
            'code' => 'booking_blocked',
            'message' => $this->holds->blockedMessage($user),
            'data' => ['blocked_until' => $user->booking_blocked_until],
        ], 429);
    }

    /** @param  list<int>  $seatIds */
    private function releaseSeatHolds(Order $order, array $seatIds): void
    {
        try {
            $this->seatHolds->release(
                (int) $order->show_id,
                $seatIds,
                $this->seatHolds->ownerForUser((int) $order->user_id),
            );
        } catch (Throwable $exception) {
            Log::warning('Could not release Redis seat holds after checkout state change.', [
                'order_id' => $order->id,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Lịch sử đặt vé của user.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        $this->holds->reconcile($request->user()->id);

        $orders = $request->user()->orders()
            ->with(['show.movie', 'show.room', 'seats.seat', 'snacks.snack'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return response()->json([
            'data' => $orders->items(),
            'next_cursor' => $orders->nextCursor()?->encode(),
        ]);
    }

    /**
     * Chi tiết đơn hàng.
     */
    public function orderDetail(int $orderId)
    {
        $order = Order::with(['show.movie', 'show.room', 'seats.seat', 'snacks.snack', 'voucher'])
            ->findOrFail($orderId);

        return response()->json(['data' => $order]);
    }
}
