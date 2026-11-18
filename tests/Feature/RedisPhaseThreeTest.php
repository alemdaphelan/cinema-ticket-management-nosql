<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Show;
use App\Models\User;
use App\Services\RedisMovieLeaderboard;
use App\Services\RedisSeatHoldService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RedisPhaseThreeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_movie_list_cache_hits_and_admin_update_invalidates_the_group(): void
    {
        $movie = Movie::factory()->create(['title' => 'Bản gốc', 'status' => 'showing']);

        $this->getJson('/api/movies?status=showing')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('data.0.title', 'Bản gốc');

        $movie->update(['title' => 'Nguồn đã đổi']);
        $this->getJson('/api/movies?status=showing')
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('data.0.title', 'Bản gốc');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->putJson("/api/admin/movies/{$movie->id}", ['title' => 'Quản trị cập nhật'])
            ->assertOk();

        $this->getJson('/api/movies?status=showing')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('data.0.title', 'Quản trị cập nhật');
    }

    public function test_review_invalidates_movie_detail_and_updates_daily_leaderboard(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['status' => 'showing']);
        $show = Show::factory()->for($movie)->create(['start_time' => now()->subHours(3), 'end_time' => now()->subHour()]);
        Order::factory()->for($user)->for($show)->create(['status' => 'paid']);

        $this->getJson("/api/movies/{$movie->id}")
            ->assertOk()->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('review_stats.total_reviews', 0);
        $this->getJson("/api/movies/{$movie->id}")->assertHeader('X-Cache', 'HIT');

        $this->actingAs($user)->postJson("/api/movies/{$movie->id}/reviews", [
            'rating' => 9,
            'comment' => 'Tích hợp Redis hoạt động tốt.',
        ])->assertCreated();

        $this->getJson("/api/movies/{$movie->id}")
            ->assertOk()->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('review_stats.total_reviews', 1);
        $this->getJson('/api/movies/leaderboard?date=2026-11-17')
            ->assertOk()
            ->assertJsonPath('data.0.movie_id', $movie->id)
            ->assertJsonPath('data.0.score', 6);
    }

    public function test_show_cache_uses_five_minute_ttl(): void
    {
        $this->travelTo('2026-11-17 08:00:00');
        $show = Show::factory()->create(['start_time' => '2026-11-18 10:00:00', 'end_time' => '2026-11-18 12:00:00']);

        $first = $this->getJson('/api/shows?date=2026-11-18')
            ->assertOk()->assertHeader('X-Cache', 'MISS')->json('data.0.start_time');
        $show->update(['start_time' => '2026-11-18 11:00:00', 'end_time' => '2026-11-18 13:00:00']);
        $this->getJson('/api/shows?date=2026-11-18')
            ->assertOk()->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('data.0.start_time', $first);

        $this->travel(301)->seconds();
        $this->getJson('/api/shows?date=2026-11-18')
            ->assertOk()->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('data.0.start_time', '2026-11-18T11:00:00.000000Z');
    }

    public function test_single_seat_hold_is_exclusive_and_expires_after_ten_minutes(): void
    {
        $this->travelTo('2026-11-17 08:00:00');
        $show = Show::factory()->create();
        $seat = Seat::factory()->for($show->room)->create(['type' => 'standard']);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $payload = ['show_id' => $show->id, 'seat_ids' => [$seat->id]];

        $this->actingAs($firstUser)->postJson('/api/orders/hold', $payload)->assertCreated();
        $this->actingAs($secondUser)->postJson('/api/orders/hold', $payload)
            ->assertConflict();

        $this->travel(601)->seconds();
        $this->actingAs($secondUser)->postJson('/api/orders/hold', $payload)->assertCreated();
    }

    public function test_multi_seat_hold_is_all_or_nothing_and_leaderboard_is_sorted(): void
    {
        $seatHolds = app(RedisSeatHoldService::class);
        $showId = 50;
        $seatHolds->acquire($showId, [1], 'owner-a', 600);

        $result = $seatHolds->acquire($showId, [1, 2], 'owner-b', 600);

        $this->assertFalse($result['acquired']);
        $this->assertSame(1, $result['conflict_seat_id']);
        $this->assertSame([1 => 'owner-a'], $seatHolds->owners($showId, [1, 2]));

        $first = Movie::factory()->create(['title' => 'Hạng hai']);
        $second = Movie::factory()->create(['title' => 'Hạng nhất']);
        $leaderboard = app(RedisMovieLeaderboard::class);
        $leaderboard->increment($first->id, 2);
        $leaderboard->increment($second->id, 7);

        $this->getJson('/api/movies/leaderboard')
            ->assertOk()
            ->assertJsonPath('data.0.movie_id', $second->id)
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.1.movie_id', $first->id);
    }
}
