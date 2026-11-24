<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Show;
use App\Models\User;
use App\Services\MongoReviewRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReviewTagsAndRoutingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_top_tags_count_real_published_reviews_once_per_tag_and_follow_changes(): void
    {
        $movie = Movie::factory()->create();
        $other = Movie::factory()->create();
        $reviews = app(MongoReviewRepository::class);
        $first = $reviews->create(['movie_id' => $movie->id, 'user_id' => 1, 'rating' => 9, 'tags' => ['A', 'A', 'B']]);
        $second = $reviews->create(['movie_id' => $movie->id, 'user_id' => 2, 'rating' => 3, 'tags' => ['A']]);
        $reviews->create(['movie_id' => $movie->id, 'user_id' => 3, 'rating' => 6]);
        $reviews->create(['movie_id' => $movie->id, 'user_id' => 4, 'rating' => 10, 'tags' => ['Hidden'], 'status' => 'hidden']);
        $reviews->create(['movie_id' => $other->id, 'user_id' => 5, 'rating' => 10, 'tags' => ['Other movie']]);

        // Rating filters and pagination must not change the movie-wide summary.
        $this->getJson("/api/movies/{$movie->id}/reviews?rating_filter=9-10&page=2")
            ->assertOk()->assertJsonPath('summary.total_reviews', 3)
            ->assertJsonPath('summary.top_tags', [
                ['tag' => 'A', 'percentage' => 67],
                ['tag' => 'B', 'percentage' => 33],
            ]);

        $reviews->updateOwned($second['id'], $movie->id, 2, ['tags' => ['B']]);
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertJsonPath('summary.top_tags.0', ['tag' => 'B', 'percentage' => 67]);
        $reviews->deleteOwned($first['id'], $movie->id, 1);
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertJsonPath('summary.top_tags', [['tag' => 'B', 'percentage' => 50]]);
    }

    public function test_empty_tags_stay_empty_and_only_the_five_most_common_tags_are_returned(): void
    {
        $movie = Movie::factory()->create();
        $this->getJson("/api/movies/{$movie->id}/reviews")->assertOk()->assertJsonPath('summary.top_tags', []);
        $reviews = app(MongoReviewRepository::class);
        $reviews->create(['movie_id' => $movie->id, 'user_id' => 1, 'rating' => 10]);
        $this->getJson("/api/movies/{$movie->id}/reviews")->assertJsonPath('summary.top_tags', []);
        foreach (['F', 'E', 'D', 'C', 'B', 'A'] as $index => $tag) {
            $reviews->create(['movie_id' => $movie->id, 'user_id' => $index + 2, 'rating' => 9, 'tags' => [$tag]]);
        }
        $this->getJson("/api/movies/{$movie->id}/reviews")
            ->assertJsonPath('summary.top_tags', array_map(fn ($tag) => ['tag' => $tag, 'percentage' => 14], ['A', 'B', 'C', 'D', 'E']));

        config(['nosql.mongodb.driver' => 'mysql']);
        $this->assertSame([], $reviews->topTags($movie->id, 7));
    }

    public function test_review_api_accepts_a_web_session(): void
    {
        [$user, $movie] = $this->eligibleReviewer();

        $this->withSession([auth('web')->getName() => $user->id])
            ->withHeader('Referer', 'http://localhost/movies/'.$movie->id)
            ->postJson("/api/movies/{$movie->id}/reviews", ['rating' => 8, 'tags' => ['#KịchBảnLôiCuốn']])
            ->assertCreated()->assertJsonPath('data.tags', ['#KịchBảnLôiCuốn']);
    }

    public function test_review_api_accepts_a_bearer_token(): void
    {
        [$user, $movie] = $this->eligibleReviewer();
        $token = $user->createToken('review-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/movies/{$movie->id}/reviews", ['rating' => 8])
            ->assertCreated()->assertJsonPath('data.user_id', $user->id);
    }

    public function test_guests_cannot_create_reviews_and_database_diagnostics_are_not_exposed(): void
    {
        $movie = Movie::factory()->create();
        $this->postJson("/api/movies/{$movie->id}/reviews", ['rating' => 8])->assertUnauthorized();
        $this->getJson('/api/test-db')->assertNotFound();
    }

    private function eligibleReviewer(): array
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create();
        $show = Show::factory()->for($movie)->create(['start_time' => now()->subHours(3), 'end_time' => now()->subHour()]);
        Order::factory()->for($user)->for($show)->create(['status' => 'paid']);

        return [$user, $movie];
    }
}
