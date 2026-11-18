<?php

namespace App\Console\Commands;

use App\Events\ActivityOccurred;
use App\Services\CassandraActivityRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CassandraPhaseFiveSmoke extends Command
{
    protected $signature = 'nosql:cassandra-smoke';

    protected $description = 'Write and query a real idempotent Cassandra activity event';

    public function handle(CassandraActivityRepository $activities): int
    {
        $eventId = (string) Str::uuid();
        $occurredAt = now()->utc();
        $event = new ActivityOccurred('PHASE5_SMOKE', 1, 1, null, ['source' => 'artisan-smoke'], 'system', 'verification', 'phase-5', $occurredAt->toIso8601String(), $eventId);

        $health = $activities->health();
        $activities->record($event);
        $activities->record($event);
        $userRows = $activities->forUser(1, $occurredAt->format('Y-m'), 'PHASE5_SMOKE', 100);
        $movieRows = $activities->forMovie(1, $occurredAt->format('Y-m-d'), 'PHASE5_SMOKE', 100);
        $userMatches = collect($userRows)->filter(fn (array $row): bool => data_get($row, 'metadata.source_event_id') === $eventId)->count();
        $movieMatches = collect($movieRows)->filter(fn (array $row): bool => data_get($row, 'metadata.source_event_id') === $eventId)->count();

        if (($health['status'] ?? null) !== 'ok' || $userMatches !== 1 || $movieMatches !== 1) {
            $this->error('Cassandra smoke test failed.');

            return self::FAILURE;
        }

        $this->info(json_encode([
            'status' => 'ok',
            'event_id' => $eventId,
            'user_partition_matches' => $userMatches,
            'movie_partition_matches' => $movieMatches,
            'consistency' => $health['consistency'] ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
