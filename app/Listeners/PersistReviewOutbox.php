<?php

namespace App\Listeners;

use App\Events\ReviewChanged;
use App\Services\MongoOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;

class PersistReviewOutbox implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public function __construct(private MongoOutboxService $outbox) {}

    public function handle(ReviewChanged $event): void
    {
        $this->outbox->record($event);
    }
}
