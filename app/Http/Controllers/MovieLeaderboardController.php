<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\RedisMovieLeaderboard;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieLeaderboardController extends Controller
{
    public function __invoke(Request $request, RedisMovieLeaderboard $leaderboard): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date'])->startOfDay() : now();
        $ranked = $leaderboard->top($date, (int) ($validated['limit'] ?? 10));
        $movies = Movie::whereKey(array_column($ranked, 'movie_id'))->get(['id', 'title', 'poster_url'])->keyBy('id');

        $data = collect($ranked)->map(function (array $row, int $index) use ($movies): array {
            $movie = $movies->get($row['movie_id']);

            return [
                'rank' => $index + 1,
                'movie_id' => $row['movie_id'],
                'title' => $movie?->title,
                'poster_url' => $movie?->poster_url,
                'score' => $row['score'],
            ];
        })->values();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'data' => $data,
        ]);
    }
}
