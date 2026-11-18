<?php

namespace App\Services;

use App\Events\ActivityOccurred;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CassandraActivityRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $events = [];

    /** @return array<string, mixed> */
    public function record(ActivityOccurred $event): array
    {
        $payload = [
            'event_id' => $event->eventId,
            'event_type' => strtoupper($event->eventType),
            'user_id' => $event->userId,
            'movie_id' => $event->movieId,
            'order_id' => $event->orderId,
            'metadata' => $event->metadata,
            'actor_type' => $event->actorType,
            'aggregate_type' => $event->aggregateType,
            'aggregate_id' => $event->aggregateId,
            'occurred_at' => $event->occurredAt ?? now()->utc()->toIso8601String(),
        ];

        if ($this->usesArray()) {
            $this->events[$event->eventId] = $payload;

            return ['event_id' => $event->eventId, 'partitions_written' => 1 + (int) ($event->userId !== null) + (int) ($event->movieId !== null)];
        }

        return $this->request()->post('/events', $payload)->throw()->json('data');
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId, string $month, ?string $eventType = null, int $limit = 50): array
    {
        if ($this->usesArray()) {
            return collect($this->events)->filter(fn (array $event): bool => $event['user_id'] === $userId
                && str_starts_with($event['occurred_at'], $month)
                && ($eventType === null || $event['event_type'] === strtoupper($eventType))
            )->sortByDesc('occurred_at')->take($limit)->values()->all();
        }

        return $this->request()->get("/users/{$userId}/activity", array_filter([
            'month' => $month,
            'event_type' => $eventType,
            'limit' => $limit,
        ], fn ($value): bool => $value !== null && $value !== ''))->throw()->json('data');
    }

    /** @return list<array<string, mixed>> */
    public function forMovie(int $movieId, string $date, ?string $eventType = null, int $limit = 50): array
    {
        if ($this->usesArray()) {
            return collect($this->events)->filter(fn (array $event): bool => $event['movie_id'] === $movieId
                && str_starts_with($event['occurred_at'], $date)
                && ($eventType === null || $event['event_type'] === strtoupper($eventType))
            )->sortByDesc('occurred_at')->take($limit)->values()->all();
        }

        return $this->request()->get("/movies/{$movieId}/events", array_filter([
            'date' => $date,
            'event_type' => $eventType,
            'limit' => $limit,
        ], fn ($value): bool => $value !== null && $value !== ''))->throw()->json('data');
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->usesArray()
            ? ['status' => 'ok', 'keyspace' => config('nosql.cassandra.keyspace'), 'consistency' => 'LOCAL_QUORUM']
            : $this->request()->get('/health')->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('nosql.cassandra.api_url'), '/'))
            ->acceptJson()
            ->connectTimeout((int) config('nosql.cassandra.connect_timeout_seconds', 5))
            ->timeout((int) config('nosql.cassandra.request_timeout_seconds', 10));
    }

    private function usesArray(): bool
    {
        return config('nosql.cassandra.driver') === 'array';
    }
}
