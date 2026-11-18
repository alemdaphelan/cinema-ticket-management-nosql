<?php

namespace Tests\Feature;

use App\Events\ReviewChanged;
use App\Listeners\PersistCassandraActivity;
use App\Listeners\PersistReviewOutbox;
use App\Listeners\ProjectNeo4jActivity;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Show;
use App\Models\User;
use App\Services\CassandraActivityRepository;
use App\Services\MongoOutboxService;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisQueryCache;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class PhaseSevenAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_record_and_replay_are_idempotent_across_cassandra_and_neo4j(): void
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['status' => 'showing', 'genre' => 'Action']);
        $event = new ReviewChanged('created', [
            'id' => 'phase7-review',
            'user_id' => $user->id,
            'movie_id' => $movie->id,
            'rating' => 9,
        ], 'phase7-outbox-event');
        $outbox = app(MongoOutboxService::class);

        $outbox->record($event);
        $outbox->record($event);

        $this->assertSame(1, $outbox->count());
        $this->assertSame(1, $outbox->pendingCount());
        $this->artisan('nosql:outbox-replay')->assertSuccessful();
        $this->artisan('nosql:outbox-replay')->assertSuccessful();

        $this->assertSame(0, $outbox->pendingCount());
        $this->assertCount(1, app(CassandraActivityRepository::class)->forUser($user->id, now()->format('Y-m'), 'REVIEW_CREATED'));
        $this->assertSame(1, app(Neo4jRecommendationRepository::class)->stats()['rated']);
    }

    public function test_outbox_listener_has_a_bounded_queue_retry_policy(): void
    {
        Queue::fake();

        event(new ReviewChanged('updated', ['id' => 'r-1', 'movie_id' => 1], 'phase7-queued-outbox'));

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PersistReviewOutbox::class
            && $job->tries === 5
            && $job->backoff === [5, 30, 120, 300]);
    }

    public function test_query_cache_bypasses_redis_when_the_cache_store_is_unavailable(): void
    {
        Cache::shouldReceive('store')->once()->andThrow(new RuntimeException('redis unavailable'));

        $result = app(RedisQueryCache::class)->remember('phase7', 'fallback', 30, fn (): array => ['source' => 'mysql']);

        $this->assertSame('bypass', $result['status']);
        $this->assertSame(['source' => 'mysql'], $result['value']);
    }

    public function test_payment_commits_in_mysql_when_auxiliary_nosql_services_are_unavailable(): void
    {
        Queue::fake();
        config()->set('nosql.redis.seat_hold_store', 'redis');
        config()->set('nosql.redis.leaderboard_store', 'redis');
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis unavailable'));

        $user = User::factory()->create();
        $movie = Movie::factory()->create();
        $show = Show::factory()->for($movie)->create();
        $seat = Seat::factory()->for($show->room)->create();
        $order = Order::factory()->for($user)->for($show)->create([
            'status' => 'pending',
            'hold_expires_at' => now()->addMinutes(10),
        ]);
        $order->seats()->create(['seat_id' => $seat->id, 'price' => $show->price]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/pay", ['payment_method' => 'vnpay'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => in_array($job->class, [
            PersistCassandraActivity::class,
            ProjectNeo4jActivity::class,
        ], true));
    }

    public function test_end_to_end_review_flow_updates_all_four_nosql_read_models(): void
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['status' => 'showing', 'genre' => 'Drama']);
        $show = Show::factory()->for($movie)->create([
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
        ]);
        Order::factory()->for($user)->for($show)->create(['status' => 'paid']);

        $this->actingAs($user)->postJson("/api/movies/{$movie->id}/reviews", [
            'rating' => 10,
            'comment' => 'Phase 7 end to end.',
        ])->assertCreated();

        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.0.rating', 10);
        $this->assertSame(1, app(MongoOutboxService::class)->count());
        $this->assertCount(1, app(CassandraActivityRepository::class)->forUser($user->id, now()->format('Y-m'), 'REVIEW_CREATED'));
        $this->assertSame(1, app(Neo4jRecommendationRepository::class)->stats()['rated']);
        $this->getJson('/api/movies/leaderboard')->assertOk()->assertJsonPath('data.0.movie_id', $movie->id);
    }
}
