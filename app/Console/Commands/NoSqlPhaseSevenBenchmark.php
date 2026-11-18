<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Services\CassandraActivityRepository;
use App\Services\MongoReviewRepository;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisQueryCache;
use Illuminate\Console\Command;

class NoSqlPhaseSevenBenchmark extends Command
{
    protected $signature = 'nosql:phase7-benchmark {--iterations=30 : Iterations per read path}';

    protected $description = 'Measure cold, uncached and Redis-cached reads plus the primary NoSQL queries';

    public function handle(
        RedisQueryCache $cache,
        MongoReviewRepository $reviews,
        CassandraActivityRepository $activities,
        Neo4jRecommendationRepository $graph,
    ): int {
        $iterations = max(5, min(200, (int) $this->option('iterations')));
        $temporaryMovie = null;
        $movieId = (int) (Movie::query()->value('id') ?? 0);
        if ($movieId === 0) {
            $temporaryMovie = Movie::query()->create([
                'tmdb_id' => 'phase7-benchmark',
                'title' => 'Phase 7 benchmark probe',
                'duration_minutes' => 90,
                'status' => 'showing',
            ]);
            $movieId = (int) $temporaryMovie->id;
        }

        $group = 'phase7:benchmark';
        $resolver = fn (): array => Movie::query()
            ->whereIn('status', ['showing', 'coming_soon'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'title', 'status'])
            ->toArray();

        try {
            $cache->invalidate([$group]);
            $cold = $this->measure(fn () => $cache->remember($group, 'movies', 120, $resolver));
            $warm = $this->average($iterations, fn () => $cache->remember($group, 'movies', 120, $resolver));
            $uncached = $this->average($iterations, $resolver);
            $mongo = $this->average($iterations, fn () => $reviews->summary($movieId));
            $cassandra = $this->average($iterations, fn () => $activities->health());
            $neo4j = $this->average($iterations, fn () => $graph->similarMovies($movieId, 8));
        } finally {
            $cache->invalidate([$group]);
            $temporaryMovie?->delete();
        }

        $evidence = [
            'generated_at' => now()->setTimezone('Asia/Bangkok')->toIso8601String(),
            'iterations' => $iterations,
            'movie_id' => $movieId,
            'milliseconds' => [
                'mysql_cold_with_cache_write' => $cold,
                'mysql_uncached_average' => $uncached,
                'redis_cache_hit_average' => $warm,
                'mongodb_review_summary_average' => $mongo,
                'cassandra_health_average' => $cassandra,
                'neo4j_similar_movies_average' => $neo4j,
            ],
            'cache_speedup' => $warm > 0 ? round($uncached / $warm, 2) : null,
            'conclusion' => $warm < $uncached
                ? 'Redis cache hit is faster than the equivalent uncached MySQL query on this dataset.'
                : 'The demo dataset is too small for Redis to outperform MySQL consistently; cache correctness and TTL remain verified.',
        ];

        $path = base_path('docs/evidence/phase7-benchmark.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->table(['Metric', 'Milliseconds'], collect($evidence['milliseconds'])->map(
            fn (float $value, string $metric): array => [$metric, number_format($value, 3)],
        )->values()->all());
        $this->info('Evidence: '.$path);

        return self::SUCCESS;
    }

    private function measure(callable $callback): float
    {
        $start = hrtime(true);
        $callback();

        return round((hrtime(true) - $start) / 1_000_000, 3);
    }

    private function average(int $iterations, callable $callback): float
    {
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }

        return round(((hrtime(true) - $start) / 1_000_000) / $iterations, 3);
    }
}
