<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisQueryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(
        private Neo4jRecommendationRepository $graph,
        private RedisQueryCache $cache,
    ) {}

    public function similar(Request $request, int $movieId): JsonResponse
    {
        Movie::query()->findOrFail($movieId);
        $validated = $request->validate(['limit' => 'nullable|integer|min:1|max:20']);
        $limit = (int) ($validated['limit'] ?? 8);
        $cached = $this->cache->remember('neo4j:similar', "{$movieId}:{$limit}", (int) config('nosql.neo4j.cache_ttl_seconds', 300), fn (): array => $this->graph->similarMovies($movieId, $limit));

        return response()->json([
            'data' => $cached['value'],
            'source_movie_id' => $movieId,
            'ranking' => 'shared_genres*3 + shared_users*2',
        ])->header('X-Cache', strtoupper($cached['status']));
    }

    public function personalized(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
            'genre' => 'nullable|string|max:100',
        ]);
        $userId = (int) $request->user()->id;
        $limit = (int) ($validated['limit'] ?? 10);
        $genre = isset($validated['genre']) ? trim($validated['genre']) : null;
        $key = hash('sha256', json_encode([$userId, $limit, $genre], JSON_THROW_ON_ERROR));
        $cached = $this->cache->remember('neo4j:recommendations', $key, (int) config('nosql.neo4j.cache_ttl_seconds', 300), fn (): array => $this->graph->recommendations($userId, $limit, $genre));

        return response()->json([
            ...$cached['value'],
            'user_id' => $userId,
            'excludes' => ['already_booked'],
            'ranking' => 'genre_affinity*3 + similar_users*2',
        ])->header('X-Cache', strtoupper($cached['status']));
    }
}
