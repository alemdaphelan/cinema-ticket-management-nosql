<?php

namespace App\Listeners;

use App\Events\ActivityOccurred;
use App\Models\Movie;
use App\Models\User;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisQueryCache;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProjectNeo4jActivity implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public function __construct(
        private Neo4jRecommendationRepository $graph,
        private RedisQueryCache $cache,
    ) {}

    public function handle(ActivityOccurred $event): void
    {
        $user = $event->userId ? User::query()->find($event->userId) : null;
        $movie = $event->movieId ? Movie::query()->find($event->movieId) : null;
        if ($user && $movie) {
            $genres = preg_split('/[,;|\/]+/', (string) $movie->genre, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->graph->syncBase([[
                'user_id' => (int) $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ]], [[
                'movie_id' => (int) $movie->id,
                'title' => $movie->title,
                'status' => $movie->status,
                'poster_url' => $movie->poster_url,
                'release_date' => $movie->release_date?->format('Y-m-d'),
                'genres' => array_values(array_unique(array_filter(array_map('trim', $genres)))),
            ]]);
        }
        $this->graph->project($event);
        $this->cache->invalidate(['neo4j:similar', 'neo4j:recommendations']);
    }
}
