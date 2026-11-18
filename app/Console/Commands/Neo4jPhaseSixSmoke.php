<?php

namespace App\Console\Commands;

use App\Events\ActivityOccurred;
use App\Services\Neo4jRecommendationRepository;
use Illuminate\Console\Command;

class Neo4jPhaseSixSmoke extends Command
{
    protected $signature = 'nosql:neo4j-smoke';

    protected $description = 'Verify Neo4j idempotency, recommendation, cold-start and logical restore';

    public function handle(Neo4jRecommendationRepository $graph): int
    {
        $base = 960000;
        $graph->setupSchema();
        $graph->syncBase([
            ['user_id' => $base + 1, 'name' => 'Phase 6 User', 'role' => 'user'],
            ['user_id' => $base + 2, 'name' => 'Phase 6 Peer', 'role' => 'user'],
            ['user_id' => $base + 3, 'name' => 'Phase 6 New User', 'role' => 'user'],
        ], [
            ['movie_id' => $base + 1, 'title' => 'Phase 6 Source', 'status' => 'showing', 'poster_url' => null, 'release_date' => null, 'genres' => ['Phase6SciFiProbe', 'Phase6ActionProbe']],
            ['movie_id' => $base + 2, 'title' => 'Phase 6 Candidate', 'status' => 'showing', 'poster_url' => null, 'release_date' => null, 'genres' => ['Phase6SciFiProbe']],
            ['movie_id' => $base + 3, 'title' => 'Phase 6 Stopped', 'status' => 'stopped', 'poster_url' => null, 'release_date' => null, 'genres' => ['Phase6SciFiProbe']],
            ['movie_id' => $base + 4, 'title' => 'Phase 6 Popular', 'status' => 'coming_soon', 'poster_url' => null, 'release_date' => null, 'genres' => ['Phase6DramaProbe']],
        ]);
        $graph->syncRelationships([
            ['user_id' => $base + 1, 'movie_id' => $base + 1, 'count' => 1, 'last_at' => now()->utc()->toIso8601String(), 'last_event_id' => 'phase6-booking-1'],
            ['user_id' => $base + 2, 'movie_id' => $base + 1, 'count' => 1, 'last_at' => now()->utc()->toIso8601String(), 'last_event_id' => 'phase6-booking-2'],
            ['user_id' => $base + 2, 'movie_id' => $base + 2, 'count' => 1, 'last_at' => now()->utc()->toIso8601String(), 'last_event_id' => 'phase6-booking-3'],
            ['user_id' => $base + 2, 'movie_id' => $base + 4, 'count' => 3, 'last_at' => now()->utc()->toIso8601String(), 'last_event_id' => 'phase6-booking-4'],
        ], []);
        $event = new ActivityOccurred('MOVIE_VIEWED', $base + 1, $base + 2, eventId: 'phase6-idempotent-view');
        $graph->project($event);
        $graph->project($event);

        $similar = $graph->similarMovies($base + 1, 5);
        $personalized = $graph->recommendations($base + 1, 5);
        $coldStart = $graph->recommendations($base + 3, 5);
        $beforeRestore = $graph->stats()['movies'] ?? 0;
        $graph->deleteMovie($base + 4);
        $graph->syncBase([], [[
            'movie_id' => $base + 4, 'title' => 'Phase 6 Popular', 'status' => 'coming_soon', 'poster_url' => null, 'release_date' => null, 'genres' => ['Phase6DramaProbe'],
        ]]);
        $afterRestore = $graph->stats()['movies'] ?? 0;
        $passed = ($similar[0]['movie_id'] ?? null) === $base + 2
            && ! collect($similar)->contains('movie_id', $base + 3)
            && ! collect($personalized['data'])->contains('movie_id', $base + 1)
            && $coldStart['strategy'] === 'cold_start_popular'
            && $beforeRestore === $afterRestore;

        $this->line(json_encode([
            'status' => $passed ? 'ok' : 'failed',
            'similar_top_movie' => $similar[0]['movie_id'] ?? null,
            'personalized_strategy' => $personalized['strategy'],
            'cold_start_strategy' => $coldStart['strategy'],
            'logical_backup_restore' => $beforeRestore === $afterRestore ? 'ok' : 'failed',
            'graph' => $graph->stats(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
