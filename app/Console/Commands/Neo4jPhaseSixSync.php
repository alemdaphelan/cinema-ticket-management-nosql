<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Order;
use App\Models\User;
use App\Services\MongoReviewRepository;
use App\Services\Neo4jRecommendationRepository;
use Illuminate\Console\Command;

class Neo4jPhaseSixSync extends Command
{
    protected $signature = 'nosql:neo4j-sync {--check : Only report the reconciliation result}';

    protected $description = 'Synchronize MySQL/MongoDB source data into the Neo4j recommendation graph';

    public function handle(Neo4jRecommendationRepository $graph, MongoReviewRepository $reviews): int
    {
        $users = User::query()->get(['id', 'name', 'role'])->map(fn (User $user): array => [
            'user_id' => (int) $user->id,
            'name' => $user->name,
            'role' => $user->role,
        ])->values()->all();
        $movieModels = Movie::query()->get();
        $movies = $movieModels->map(fn (Movie $movie): array => $this->movieRow($movie))->values()->all();
        $bookings = Order::query()
            ->whereIn('status', ['paid', 'completed'])
            ->with('show:id,movie_id')
            ->get()
            ->filter(fn (Order $order): bool => (bool) $order->show)
            ->groupBy(fn (Order $order): string => "{$order->user_id}:{$order->show->movie_id}")
            ->map(function ($orders): array {
                $latest = $orders->sortByDesc('updated_at')->first();

                return [
                    'user_id' => (int) $latest->user_id,
                    'movie_id' => (int) $latest->show->movie_id,
                    'count' => $orders->count(),
                    'last_at' => $latest->updated_at?->utc()->toIso8601String() ?? now()->utc()->toIso8601String(),
                    'last_event_id' => 'order:'.$latest->id,
                ];
            })->values()->all();
        $ratings = [];
        foreach ($movieModels as $movie) {
            $page = 1;
            do {
                $batch = $reviews->paginate((int) $movie->id, 'all', $page, 100);
                foreach ($batch['data'] as $review) {
                    $ratings[] = [
                        'user_id' => (int) $review['user_id'],
                        'movie_id' => (int) $review['movie_id'],
                        'rating' => (int) $review['rating'],
                        'at' => $this->dateString($review['updated_at'] ?? null),
                        'last_event_id' => 'review:'.($review['id'] ?? "{$review['user_id']}:{$review['movie_id']}"),
                    ];
                }
                $page++;
            } while ($page <= $batch['last_page']);
        }

        if (! $this->option('check')) {
            $graph->setupSchema();
            $graph->syncBase($users, $movies);
            $graph->syncRelationships($bookings, $ratings);
        }

        $stats = $graph->stats();
        $expected = [
            'users' => count($users),
            'movies' => count($movies),
            'booked' => count($bookings),
            'rated' => count($ratings),
        ];
        $missing = collect($expected)->filter(fn (int $count, string $key): bool => (int) ($stats[$key] ?? 0) < $count)->all();
        $this->line(json_encode(['status' => $missing === [] ? 'ok' : 'drift', 'expected_minimum' => $expected, 'graph' => $stats, 'missing' => $missing], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function movieRow(Movie $movie): array
    {
        $genres = preg_split('/[,;|\/]+/', (string) $movie->genre, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'movie_id' => (int) $movie->id,
            'title' => $movie->title,
            'status' => $movie->status,
            'poster_url' => $movie->poster_url,
            'release_date' => $movie->release_date?->format('Y-m-d'),
            'genres' => array_values(array_unique(array_filter(array_map('trim', $genres)))),
        ];
    }

    private function dateString(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format(DATE_ATOM) : now()->utc()->toIso8601String();
    }
}
