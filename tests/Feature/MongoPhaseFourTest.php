<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Review;
use App\Models\Show;
use App\Models\User;
use App\Services\MongoChatSessionService;
use App\Services\MongoOutboxService;
use App\Services\MongoReviewRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MongoPhaseFourTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_review_create_is_unique_invalidates_cache_and_records_outbox_event(): void
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create(['status' => 'showing']);
        $show = Show::factory()->for($movie)->create([
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
        ]);
        Order::factory()->for($user)->for($show)->create(['status' => 'paid']);

        $this->getJson("/api/movies/{$movie->id}")->assertOk()->assertHeader('X-Cache', 'MISS');
        $this->getJson("/api/movies/{$movie->id}")->assertHeader('X-Cache', 'HIT');

        $response = $this->actingAs($user)->postJson("/api/movies/{$movie->id}/reviews", [
            'rating' => 9,
            'comment' => 'MongoDB lưu đánh giá thành công.',
            'tags' => ['hài lòng'],
        ])->assertCreated();

        $response->assertJsonPath('data.rating', 9)
            ->assertJsonPath('data.comment', 'MongoDB lưu đánh giá thành công.');
        $this->getJson("/api/movies/{$movie->id}")
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('review_stats.total_reviews', 1)
            ->assertJsonPath('review_stats.average_rating', 9);
        $this->actingAs($user)->postJson("/api/movies/{$movie->id}/reviews", [
            'rating' => 8,
        ])->assertUnprocessable();
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertOk()
            ->assertJsonPath('summary.total_reviews', 1)
            ->assertJsonPath('summary.satisfaction_rate', 100);
        $this->assertSame(1, app(MongoOutboxService::class)->count());
    }

    public function test_only_owner_can_update_and_delete_a_review(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $movie = Movie::factory()->create();
        $show = Show::factory()->for($movie)->create(['start_time' => now()->subHours(2)]);
        Order::factory()->for($owner)->for($show)->create(['status' => 'paid']);

        $reviewId = $this->actingAs($owner)->postJson("/api/movies/{$movie->id}/reviews", [
            'rating' => 8,
            'comment' => 'Bản đầu',
        ])->assertCreated()->json('data.id');

        $this->actingAs($other)->patchJson("/api/movies/{$movie->id}/reviews/{$reviewId}", [
            'rating' => 4,
        ])->assertForbidden();
        $this->actingAs($owner)->patchJson("/api/movies/{$movie->id}/reviews/{$reviewId}", [
            'rating' => 6,
            'comment' => 'Đã sửa',
        ])->assertOk()->assertJsonPath('data.rating', 6)->assertJsonPath('data.comment', 'Đã sửa');
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertJsonPath('summary.average_rating', 6);

        $this->actingAs($other)->deleteJson("/api/movies/{$movie->id}/reviews/{$reviewId}")->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/movies/{$movie->id}/reviews/{$reviewId}")->assertOk();
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertJsonPath('summary.total_reviews', 0);
        $this->assertSame(3, app(MongoOutboxService::class)->count());
    }

    public function test_review_aggregation_returns_breakdown_satisfaction_filter_and_rating_sort(): void
    {
        $first = Movie::factory()->create(['title' => 'Điểm cao', 'status' => 'showing']);
        $second = Movie::factory()->create(['title' => 'Điểm thấp', 'status' => 'showing']);
        $reviews = app(MongoReviewRepository::class);
        foreach ([10, 8, 5, 3] as $index => $rating) {
            $reviews->create([
                'movie_id' => $first->id,
                'user_id' => 1000 + $index,
                'rating' => $rating,
                'content' => "Review {$rating}",
            ]);
        }
        $reviews->create([
            'movie_id' => $second->id,
            'user_id' => 2000,
            'rating' => 2,
            'content' => 'Review thấp',
        ]);

        $this->getJson("/api/movies/{$first->id}/reviews?rating_filter=9-10")
            ->assertOk()
            ->assertJsonPath('summary.average_rating', 6.5)
            ->assertJsonPath('summary.satisfaction_rate', 50)
            ->assertJsonPath('summary.breakdown.stars_9_10.count', 1)
            ->assertJsonPath('total', 1);
        $this->getJson('/api/movies?status=showing&sort=rating')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.reviews_avg_rating', 6.5)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_legacy_review_migration_is_idempotent_and_has_rollback(): void
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();
        Review::query()->create([
            'user_id' => $user->id,
            'movie_id' => $movie->id,
            'rating' => 7,
            'comment' => 'Dữ liệu SQL cũ',
        ]);
        $reviews = app(MongoReviewRepository::class);

        $this->assertSame(0, Artisan::call('nosql:mongo-migrate-reviews'));
        $this->assertSame(1, $reviews->countSource('mysql_migration'));
        $this->assertSame(0, Artisan::call('nosql:mongo-migrate-reviews'));
        $this->assertSame(1, $reviews->countSource('mysql_migration'));
        $this->assertSame(0, Artisan::call('nosql:mongo-migrate-reviews', ['--rollback' => true]));
        $this->assertSame(0, $reviews->countSource('mysql_migration'));
        $this->assertDatabaseHas('reviews', ['id' => 1, 'comment' => 'Dữ liệu SQL cũ']);
    }

    public function test_chat_history_is_kept_by_session_with_expiration_and_owner_protection(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Trả lời từ Gemini giả lập']]],
                ]],
            ]),
        ]);
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $sessionId = $this->actingAs($owner)->postJson('/chatbot/chat', [
            'message' => 'Có phim gì?',
        ])->assertOk()->assertJsonPath('reply', 'Trả lời từ Gemini giả lập')->json('session_id');
        $this->actingAs($owner)->postJson('/chatbot/chat', [
            'session_id' => $sessionId,
            'message' => 'Có suất chiếu nào?',
        ])->assertOk()->assertJsonPath('session_id', $sessionId);

        $session = app(MongoChatSessionService::class)->find($sessionId);
        $this->assertCount(4, $session['messages']);
        $this->assertGreaterThan(now()->addHours(23)->timestamp, strtotime($session['expires_at']));
        $this->actingAs($other)->postJson('/chatbot/chat', [
            'session_id' => $sessionId,
            'message' => 'Chiếm phiên',
        ])->assertForbidden();
    }

    public function test_mongodb_setup_command_is_safe_for_the_array_test_driver(): void
    {
        $this->assertSame(0, Artisan::call('nosql:mongo-setup'));
        $this->assertStringContainsString('in-memory test driver', Artisan::output());
    }
}
