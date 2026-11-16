<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class BookingHoldService
{
    public const EXPIRATION_LIMIT = 3;

    private const COOLDOWN_MINUTES = 15;

    /**
     * @template TResult
     *
     * @param  Closure(User): TResult  $action
     * @return TResult
     */
    public function withUser(int $userId, Closure $action): mixed
    {
        return DB::transaction(function () use ($userId, $action) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $this->expireDueHolds($user);

            return $action($user);
        }, 3);
    }

    public function reconcile(int $userId): User
    {
        return $this->withUser($userId, static fn (User $user): User => $user);
    }

    public function isBlocked(User $user): bool
    {
        return $user->booking_blocked_until?->isFuture() ?? false;
    }

    public function blockedMessage(User $user): string
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->booking_blocked_until, false) / 60));

        return "Bạn đã để hết thời gian giữ ghế 3 lần. Tài khoản tạm khóa đặt vé. Vui lòng thử lại sau {$minutes} phút.";
    }

    public function expirationMessage(User $user): string
    {
        $remaining = self::EXPIRATION_LIMIT - $user->expired_booking_holds;

        return "Ghế đã được giải phóng. Bạn có thể chọn ghế lại cho suất chiếu này. Tài khoản sẽ bị tạm khóa đặt vé sau {$remaining} lần hết hạn nữa.";
    }

    private function expireDueHolds(User $user): void
    {
        $orders = $user->orders()
            ->where('status', 'pending')
            ->where('hold_expires_at', '<=', now())
            ->orderBy('hold_expires_at')
            ->lockForUpdate()
            ->get(['id', 'hold_expires_at']);

        if ($orders->isEmpty()) {
            return;
        }

        $user->orders()->whereKey($orders->modelKeys())->update([
            'status' => 'cancelled',
            'cancel_reason' => Order::CANCEL_REASON_HOLD_EXPIRED,
        ]);

        foreach ($orders as $order) {
            if ($user->booking_blocked_until && $order->hold_expires_at->lte($user->booking_blocked_until)) {
                continue;
            }

            $user->expired_booking_holds++;

            if ($user->expired_booking_holds >= self::EXPIRATION_LIMIT) {
                $user->expired_booking_holds = 0;
                $user->booking_blocked_until = $order->hold_expires_at->copy()->addMinutes(self::COOLDOWN_MINUTES);
            }
        }

        $user->save();
    }
}
