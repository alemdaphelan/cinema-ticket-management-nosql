<?php

namespace App\Console\Commands;

use App\Services\RedisMovieLeaderboard;
use App\Services\RedisQueryCache;
use App\Services\RedisSeatHoldService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

#[Signature('nosql:redis-smoke')]
#[Description('Kiểm tra cache, TTL, giữ ghế nguyên tử và leaderboard Redis của giai đoạn 3')]
class RedisPhaseThreeSmoke extends Command
{
    public function handle(
        RedisQueryCache $cache,
        RedisSeatHoldService $seatHolds,
        RedisMovieLeaderboard $leaderboard,
    ): int {
        config()->set([
            'nosql.redis.cache_store' => 'redis',
            'nosql.redis.seat_hold_store' => 'redis',
            'nosql.redis.leaderboard_store' => 'redis',
        ]);

        $redis = Redis::connection((string) config('nosql.redis.connection', 'default'));
        $this->assertSame('PONG', strtoupper((string) $redis->command('ping')), 'Redis PING failed');

        $cache->invalidate(['smoke:phase3']);
        $sourceCalls = 0;
        $started = microtime(true);
        $miss = $cache->remember('smoke:phase3', 'benchmark', 10, function () use (&$sourceCalls): array {
            $sourceCalls++;
            usleep(100000);

            return ['ready' => true];
        });
        $missMilliseconds = round((microtime(true) - $started) * 1000, 2);

        $started = microtime(true);
        $hit = $cache->remember('smoke:phase3', 'benchmark', 10, function () use (&$sourceCalls): array {
            $sourceCalls++;

            return ['ready' => false];
        });
        $hitMilliseconds = round((microtime(true) - $started) * 1000, 2);

        $this->assertSame('miss', $miss['status'], 'The first cache read was not a miss');
        $this->assertSame('hit', $hit['status'], 'The second cache read was not a hit');
        $this->assertSame(1, $sourceCalls, 'The cached resolver ran more than once');

        $ttlKey = 'nosql:smoke:phase3:ttl';
        Cache::store('redis')->put($ttlKey, 'expires', 1);
        usleep(1200000);
        if (Cache::store('redis')->has($ttlKey)) {
            throw new RuntimeException('Redis TTL did not expire the smoke key');
        }

        $showId = 990000001;
        $ownerA = 'smoke:owner:a';
        $ownerB = 'smoke:owner:b';
        $seatHolds->release($showId, [1, 2, 3, 4], $ownerA);
        $seatHolds->release($showId, [1, 2, 3, 4], $ownerB);

        $single = $seatHolds->acquire($showId, [1], $ownerA, 30);
        $singleConflict = $seatHolds->acquire($showId, [1], $ownerB, 30);
        $multi = $seatHolds->acquire($showId, [2, 3], $ownerA, 30);
        $multiConflict = $seatHolds->acquire($showId, [3, 4], $ownerB, 30);
        $owners = $seatHolds->owners($showId, [1, 2, 3, 4]);

        $this->assertSame(true, $single['acquired'], 'SET NX EX did not acquire a free seat');
        $this->assertSame(false, $singleConflict['acquired'], 'A competing owner acquired the same seat');
        $this->assertSame(true, $multi['acquired'], 'Lua did not acquire the free seat group');
        $this->assertSame(false, $multiConflict['acquired'], 'Lua accepted a partially conflicting group');
        if (isset($owners[4])) {
            throw new RuntimeException('Lua left a partial hold after rejecting a seat group');
        }

        $date = now()->startOfDay();
        $leaderboard->clear($date);
        $leaderboard->increment(101, 2, $date);
        $leaderboard->increment(102, 5, $date);
        $top = $leaderboard->top($date, 2);
        $this->assertSame(102, $top[0]['movie_id'] ?? null, 'Sorted Set did not return the highest score first');

        $seatHolds->release($showId, [1, 2, 3, 4], $ownerA);
        $seatHolds->release($showId, [1, 2, 3, 4], $ownerB);
        $leaderboard->clear($date);

        $this->table(
            ['Check', 'Result'],
            [
                ['Cache miss', "{$missMilliseconds} ms"],
                ['Cache hit', "{$hitMilliseconds} ms"],
                ['TTL', 'expired after 1 second'],
                ['Single seat', 'exclusive'],
                ['Multi seat Lua', 'atomic'],
                ['Leaderboard', 'sorted descending'],
            ],
        );
        $this->info('Redis phase 3 smoke tests passed.');

        return self::SUCCESS;
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException($message.'; expected '.var_export($expected, true).', got '.var_export($actual, true));
        }
    }
}
