<?php

namespace App\Listeners;

use App\Events\ActivityOccurred;
use App\Services\CassandraActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class PersistCassandraActivity implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public function __construct(private CassandraActivityRepository $activities) {}

    public function handle(ActivityOccurred $event): void
    {
        $this->activities->record($event);
    }
}
