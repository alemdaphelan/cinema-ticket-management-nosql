<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisMovieLeaderboard
{
    /** @var array<string, array<int, float>> */
    private array $arrayScores = [];

    public function increment(int $movieId, float $score, ?CarbonInterface $date = null): void
    {
        $dateKey = $this->dateKey($date);

        if ($this->usesArrayStore()) {
            $this->arrayScores[$dateKey][$movieId] = ($this->arrayScores[$dateKey][$movieId] ?? 0) + $score;

            return;
        }

        try {
            $redis = Redis::connection($this->connection());
            $redis->command('zincrby', [$this->key($dateKey), $score, (string) $movieId]);
            $redis->command('expire', [$this->key($dateKey), (int) config('nosql.redis.leaderboard_ttl_seconds', 2678400)]);
        } catch (Throwable $exception) {
            Log::warning('Redis movie leaderboard increment failed.', [
                'movie_id' => $movieId,
                'exception' => $exception::class,
            ]);
        }
    }

    /** @return list<array{movie_id: int, score: float}> */
    public function top(?CarbonInterface $date = null, int $limit = 10): array
    {
        $dateKey = $this->dateKey($date);
        $limit = max(1, min(50, $limit));

        if ($this->usesArrayStore()) {
            $scores = $this->arrayScores[$dateKey] ?? [];
            arsort($scores, SORT_NUMERIC);

            return collect(array_slice($scores, 0, $limit, true))
                ->map(fn (float|int $score, int|string $movieId): array => [
                    'movie_id' => (int) $movieId,
                    'score' => (float) $score,
                ])->values()->all();
        }

        try {
            $rows = Redis::connection($this->connection())
                ->command('zrevrange', [$this->key($dateKey), 0, $limit - 1, 'WITHSCORES']);

            $result = [];
            foreach ($rows as $movieId => $score) {
                $result[] = ['movie_id' => (int) $movieId, 'score' => (float) $score];
            }

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Redis movie leaderboard read failed.', ['exception' => $exception::class]);

            return [];
        }
    }

    public function clear(?CarbonInterface $date = null): void
    {
        $dateKey = $this->dateKey($date);
        if ($this->usesArrayStore()) {
            unset($this->arrayScores[$dateKey]);

            return;
        }

        Redis::connection($this->connection())->command('del', [$this->key($dateKey)]);
    }

    private function key(string $date): string
    {
        return "nosql:leaderboard:movies:{$date}";
    }

    private function dateKey(?CarbonInterface $date): string
    {
        return ($date ?? now())->format('Y-m-d');
    }

    private function usesArrayStore(): bool
    {
        return config('nosql.redis.leaderboard_store') === 'array';
    }

    private function connection(): string
    {
        return (string) config('nosql.redis.connection', 'default');
    }
}
