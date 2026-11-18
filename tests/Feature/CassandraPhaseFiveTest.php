<?php

namespace Tests\Feature;

use App\Events\ActivityOccurred;
use App\Listeners\PersistCassandraActivity;
use App\Models\User;
use App\Services\CassandraActivityRepository;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CassandraPhaseFiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_id_makes_retries_idempotent_and_partitions_are_queryable(): void
    {
        $repository = app(CassandraActivityRepository::class);
        $event = new ActivityOccurred(
            'ORDER_PAID', 7, 11, 13, ['source' => 'test'], 'user', 'order', '13',
            '2026-11-17T10:00:00+00:00', '123e4567-e89b-42d3-a456-426614174000',
        );

        $repository->record($event);
        $repository->record($event);

        $this->assertCount(1, $repository->forUser(7, '2026-11', 'ORDER_PAID'));
        $this->assertCount(1, $repository->forMovie(11, '2026-11-17', 'ORDER_PAID'));
        $this->assertSame('123e4567-e89b-42d3-a456-426614174000', $repository->forUser(7, '2026-11')[0]['event_id']);
    }

    public function test_history_endpoint_is_partition_bounded_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $repository = app(CassandraActivityRepository::class);
        $repository->record(new ActivityOccurred('MOVIE_VIEWED', $user->id, 5, occurredAt: '2026-11-17T08:00:00+00:00'));
        $repository->record(new ActivityOccurred('MOVIE_VIEWED', $other->id, 5, occurredAt: '2026-11-17T09:00:00+00:00'));
        Sanctum::actingAs($user);

        $this->getJson('/api/activity/history?month=2026-11&event_type=MOVIE_VIEWED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('partition.user_id', $user->id)
            ->assertJsonPath('allow_filtering', false);
    }

    public function test_history_validation_rejects_unbounded_or_invalid_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/activity/history?month=17-11-2026&limit=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month', 'limit']);
    }

    public function test_activity_listener_is_queued_with_retry_policy(): void
    {
        Queue::fake();

        ActivityOccurred::dispatch('MOVIE_VIEWED', 1, 2);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PersistCassandraActivity::class
            && $job->tries === 5
            && $job->backoff === [5, 30, 120, 300]
        );
    }

    public function test_cassandra_retention_and_consistency_are_explicit(): void
    {
        $this->assertSame('LOCAL_QUORUM', config('nosql.cassandra.consistency'));
        $this->assertSame(7776000, config('nosql.cassandra.activity_ttl_seconds'));
        $this->assertSame(15552000, config('nosql.cassandra.audit_ttl_seconds'));
        $this->assertSame('ok', app(CassandraActivityRepository::class)->health()['status']);
    }
}
