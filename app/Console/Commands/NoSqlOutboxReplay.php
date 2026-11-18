<?php

namespace App\Console\Commands;

use App\Events\ActivityOccurred;
use App\Services\MongoOutboxService;
use Illuminate\Console\Command;
use MongoDB\BSON\UTCDateTime;
use Throwable;

class NoSqlOutboxReplay extends Command
{
    protected $signature = 'nosql:outbox-replay {--limit= : Maximum number of pending events}';

    protected $description = 'Replay pending MongoDB outbox events to idempotent Cassandra and Neo4j projections';

    public function handle(MongoOutboxService $outbox): int
    {
        $limit = (int) ($this->option('limit') ?: config('nosql.mongodb.outbox_batch_size', 100));
        $published = 0;
        $failed = 0;

        foreach ($outbox->pending($limit) as $document) {
            $eventId = (string) $document['event_id'];
            try {
                $payload = (array) ($document['payload'] ?? []);
                event(new ActivityOccurred(
                    strtoupper(str_replace('.', '_', (string) $document['event_type'])),
                    isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                    isset($payload['movie_id']) ? (int) $payload['movie_id'] : null,
                    isset($payload['order_id']) ? (int) $payload['order_id'] : null,
                    ['review_id' => $payload['id'] ?? null, 'rating' => $payload['rating'] ?? null],
                    'outbox',
                    'review',
                    (string) ($document['aggregate_id'] ?? ''),
                    $this->dateString($document['occurred_at'] ?? null),
                    $eventId,
                ));
                $outbox->markPublished($eventId);
                $published++;
            } catch (Throwable $exception) {
                $outbox->markFailed($eventId, $exception);
                $this->error("{$eventId}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->table(['Published', 'Failed', 'Pending'], [[$published, $failed, $outbox->pendingCount()]]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }

        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (is_string($value) ? $value : null);
    }
}
