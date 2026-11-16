<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BookingHoldService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseSeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(BookingHoldService $holds): void
    {
        $userId = Order::query()->whereKey($this->orderId)->value('user_id');

        if ($userId !== null) {
            $holds->reconcile($userId);
        }
    }
}
