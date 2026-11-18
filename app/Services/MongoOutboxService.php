<?php

namespace App\Services;

use App\Events\ReviewChanged;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Throwable;

class MongoOutboxService
{
    /** @var array<string, array<string, mixed>> */
    private array $events = [];

    public function record(ReviewChanged $event): void
    {
        $document = [
            'event_id' => $event->eventId,
            'event_type' => "review.{$event->action}",
            'aggregate_id' => (string) ($event->review['id'] ?? ''),
            'payload' => $event->review,
            'targets' => ['cassandra', 'neo4j'],
            'occurred_at' => now(),
            'published_at' => null,
            'attempts' => 0,
        ];

        if ($this->usesArray()) {
            $this->events[$event->eventId] ??= $document;

            return;
        }

        $document['occurred_at'] = new UTCDateTime($document['occurred_at']);
        $this->collection()->updateOne(
            ['event_id' => $event->eventId],
            ['$setOnInsert' => $document],
            ['upsert' => true],
        );
    }

    public function count(): int
    {
        if ($this->usesArray()) {
            return count($this->events);
        }

        return $this->collection()->countDocuments();
    }

    /** @return list<array<string, mixed>> */
    public function pending(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        if ($this->usesArray()) {
            return collect($this->events)
                ->filter(fn (array $event): bool => $event['published_at'] === null)
                ->sortBy('occurred_at')
                ->take($limit)
                ->values()
                ->all();
        }

        return array_map(
            fn ($document): array => $document->getArrayCopy(),
            iterator_to_array($this->collection()->find(
                ['published_at' => null],
                ['sort' => ['occurred_at' => 1], 'limit' => $limit],
            )),
        );
    }

    public function pendingCount(): int
    {
        if ($this->usesArray()) {
            return count(array_filter($this->events, fn (array $event): bool => $event['published_at'] === null));
        }

        return $this->collection()->countDocuments(['published_at' => null]);
    }

    public function markPublished(string $eventId): void
    {
        if ($this->usesArray()) {
            if (isset($this->events[$eventId])) {
                $this->events[$eventId]['published_at'] = now();
                $this->events[$eventId]['last_error'] = null;
            }

            return;
        }

        $this->collection()->updateOne(
            ['event_id' => $eventId],
            ['$set' => ['published_at' => new UTCDateTime(now()), 'last_error' => null]],
        );
    }

    public function markFailed(string $eventId, Throwable $exception): void
    {
        $message = mb_substr($exception->getMessage(), 0, 1000);
        if ($this->usesArray()) {
            if (isset($this->events[$eventId])) {
                $this->events[$eventId]['attempts']++;
                $this->events[$eventId]['last_error'] = $message;
            }

            return;
        }

        $this->collection()->updateOne(
            ['event_id' => $eventId],
            ['$inc' => ['attempts' => 1], '$set' => ['last_error' => $message]],
        );
    }

    private function collection(): Collection
    {
        $connection = DB::connection((string) config('nosql.mongodb.connection', 'mongodb'));
        if (! $connection instanceof Connection) {
            throw new \RuntimeException('The configured MongoDB connection is invalid.');
        }

        return $connection->getCollection((string) config('nosql.mongodb.outbox_collection', 'integration_outbox'));
    }

    private function usesArray(): bool
    {
        return config('nosql.mongodb.driver') === 'array';
    }
}
