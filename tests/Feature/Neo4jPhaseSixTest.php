<?php

namespace Tests\Feature;

use App\Events\ActivityOccurred;
use App\Listeners\ProjectNeo4jActivity;
use App\Models\Movie;
use App\Models\User;
use App\Services\Neo4jRecommendationRepository;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Neo4jPhaseSixTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_sync_and_relationship_projection_are_idempotent(): void
    {
        $graph = app(Neo4jRecommendationRepository::class);
        $graph->syncBase($this->users(), $this->movies());
        $event = new ActivityOccurred('MOVIE_VIEWED', 1, 10, eventId: 'same-event');

        $graph->project($event);
        $graph->project($event);

        $this->assertSame(3, $graph->stats()['users']);
        $this->assertSame(4, $graph->stats()['movies']);
        $this->assertSame(1, $graph->stats()['viewed']);
        $this->assertSame(5, $graph->stats()['in_genre']);
    }

    public function test_similar_movies_rank_shared_genres_and_users_and_exclude_stopped(): void
    {
        $graph = $this->seedGraph();

        $similar = $graph->similarMovies(10);

        $this->assertSame(11, $similar[0]['movie_id']);
        $this->assertSame(5, $similar[0]['score']);
        $this->assertFalse(collect($similar)->contains('movie_id', 12));
    }

    public function test_personalized_recommendations_exclude_booked_movies_and_expose_score(): void
    {
        $graph = $this->seedGraph();

        $result = $graph->recommendations(1);

        $this->assertSame('personalized_graph', $result['strategy']);
        $this->assertFalse(collect($result['data'])->contains('movie_id', 10));
        $this->assertSame(11, $result['data'][0]['movie_id']);
        $this->assertArrayHasKey('score', $result['data'][0]);
    }

    public function test_new_user_gets_popular_cold_start_with_optional_genre(): void
    {
        $graph = $this->seedGraph();

        $result = $graph->recommendations(3, 10, 'Drama');

        $this->assertSame('cold_start_popular', $result['strategy']);
        $this->assertSame([13], collect($result['data'])->pluck('movie_id')->all());
    }

    public function test_two_recommendation_apis_are_cached_and_authenticated_as_required(): void
    {
        $user = User::factory()->create();
        $source = Movie::factory()->create(['status' => 'showing', 'genre' => 'Sci-Fi']);
        $candidate = Movie::factory()->create(['status' => 'coming_soon', 'genre' => 'Sci-Fi']);
        $graph = app(Neo4jRecommendationRepository::class);
        $graph->syncBase([
            ['user_id' => $user->id, 'name' => $user->name, 'role' => 'user'],
        ], [
            ['movie_id' => $source->id, 'title' => $source->title, 'status' => $source->status, 'poster_url' => null, 'release_date' => null, 'genres' => ['Sci-Fi']],
            ['movie_id' => $candidate->id, 'title' => $candidate->title, 'status' => $candidate->status, 'poster_url' => null, 'release_date' => null, 'genres' => ['Sci-Fi']],
        ]);

        $this->getJson("/api/movies/{$source->id}/similar")
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('data.0.movie_id', $candidate->id);
        $this->getJson("/api/movies/{$source->id}/similar")->assertHeader('X-Cache', 'HIT');
        $this->getJson('/api/recommendations')->assertUnauthorized();

        Sanctum::actingAs($user);
        $this->getJson('/api/recommendations')
            ->assertOk()
            ->assertJsonPath('strategy', 'cold_start_popular')
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_activity_projection_is_queued_with_retry_policy(): void
    {
        Queue::fake();

        ActivityOccurred::dispatch('ORDER_PAID', 1, 10);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === ProjectNeo4jActivity::class
            && $job->tries === 5
            && $job->backoff === [5, 30, 120, 300]
        );
    }

    public function test_neo4j_operational_settings_are_explicit(): void
    {
        $this->assertSame('array', config('nosql.neo4j.driver'));
        $this->assertSame(300, config('nosql.neo4j.cache_ttl_seconds'));
        $this->assertSame(15, config('nosql.neo4j.request_timeout_seconds'));
    }

    private function seedGraph(): Neo4jRecommendationRepository
    {
        $graph = app(Neo4jRecommendationRepository::class);
        $graph->syncBase($this->users(), $this->movies());
        $now = now()->utc()->toIso8601String();
        $graph->syncRelationships([
            ['user_id' => 1, 'movie_id' => 10, 'count' => 1, 'last_at' => $now, 'last_event_id' => 'a'],
            ['user_id' => 2, 'movie_id' => 10, 'count' => 1, 'last_at' => $now, 'last_event_id' => 'b'],
            ['user_id' => 2, 'movie_id' => 11, 'count' => 1, 'last_at' => $now, 'last_event_id' => 'c'],
            ['user_id' => 2, 'movie_id' => 13, 'count' => 3, 'last_at' => $now, 'last_event_id' => 'd'],
        ], []);

        return $graph;
    }

    /** @return list<array<string, mixed>> */
    private function users(): array
    {
        return [
            ['user_id' => 1, 'name' => 'An', 'role' => 'user'],
            ['user_id' => 2, 'name' => 'Binh', 'role' => 'user'],
            ['user_id' => 3, 'name' => 'Chi', 'role' => 'user'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function movies(): array
    {
        return [
            ['movie_id' => 10, 'title' => 'Source', 'status' => 'showing', 'poster_url' => null, 'release_date' => null, 'genres' => ['Sci-Fi', 'Action']],
            ['movie_id' => 11, 'title' => 'Candidate', 'status' => 'showing', 'poster_url' => null, 'release_date' => null, 'genres' => ['Sci-Fi']],
            ['movie_id' => 12, 'title' => 'Stopped', 'status' => 'stopped', 'poster_url' => null, 'release_date' => null, 'genres' => ['Sci-Fi']],
            ['movie_id' => 13, 'title' => 'Popular', 'status' => 'coming_soon', 'poster_url' => null, 'release_date' => null, 'genres' => ['Drama']],
        ];
    }
}
