<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisSeatHoldService
{
    /** @var array<string, array{owner: string, expires_at: int}> */
    private array $arrayHolds = [];

    /**
     * @param  list<int>  $seatIds
     * @return array{acquired: bool, conflict_seat_id: int|null}
     */
    public function acquire(int $showId, array $seatIds, string $owner, int $ttlSeconds): array
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        $ttlSeconds = max(1, $ttlSeconds);

        if ($this->usesArrayStore()) {
            return $this->acquireInArray($showId, $seatIds, $owner, $ttlSeconds);
        }

        $keys = array_map(fn (int $seatId): string => $this->key($showId, $seatId), $seatIds);
        if (count($keys) === 1) {
            return $this->acquireSingleInRedis($keys[0], $seatIds[0], $owner, $ttlSeconds);
        }

        $script = <<<'LUA'
for index, key in ipairs(KEYS) do
    local current = redis.call('GET', key)
    if current and current ~= ARGV[1] then
        return {0, index}
    end
end

for _, key in ipairs(KEYS) do
    redis.call('SET', key, ARGV[1], 'EX', ARGV[2])
end

return {1, 0}
LUA;

        $result = Redis::connection($this->connection())
            ->command('eval', array_merge([$script, count($keys)], $keys, [$owner, $ttlSeconds]));

        $acquired = (int) ($result[0] ?? 0) === 1;
        $conflictIndex = (int) ($result[1] ?? 0);

        return [
            'acquired' => $acquired,
            'conflict_seat_id' => $acquired || $conflictIndex < 1 ? null : $seatIds[$conflictIndex - 1],
        ];
    }

    /** @return array{acquired: bool, conflict_seat_id: int|null} */
    private function acquireSingleInRedis(string $key, int $seatId, string $owner, int $ttlSeconds): array
    {
        $redis = Redis::connection($this->connection());
        $result = $redis->command('set', [$key, $owner, 'EX', $ttlSeconds, 'NX']);
        if ($result !== null) {
            return ['acquired' => true, 'conflict_seat_id' => null];
        }

        $current = $redis->command('get', [$key]);
        if ($current === $owner) {
            $redis->command('expire', [$key, $ttlSeconds]);

            return ['acquired' => true, 'conflict_seat_id' => null];
        }

        if ($current === null && $redis->command('set', [$key, $owner, 'EX', $ttlSeconds, 'NX']) !== null) {
            return ['acquired' => true, 'conflict_seat_id' => null];
        }

        return ['acquired' => false, 'conflict_seat_id' => $seatId];
    }

    /** @param  list<int>  $seatIds */
    public function release(int $showId, array $seatIds, string $owner): void
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if ($seatIds === []) {
            return;
        }

        if ($this->usesArrayStore()) {
            foreach ($seatIds as $seatId) {
                $key = $this->key($showId, $seatId);
                if (($this->activeArrayHold($key)['owner'] ?? null) === $owner) {
                    unset($this->arrayHolds[$key]);
                }
            }

            return;
        }

        $keys = array_map(fn (int $seatId): string => $this->key($showId, $seatId), $seatIds);
        $script = <<<'LUA'
local released = 0
for _, key in ipairs(KEYS) do
    if redis.call('GET', key) == ARGV[1] then
        released = released + redis.call('DEL', key)
    end
end
return released
LUA;

        Redis::connection($this->connection())
            ->command('eval', array_merge([$script, count($keys)], $keys, [$owner]));
    }

    /**
     * @param  list<int>  $seatIds
     * @return array<int, string>
     */
    public function owners(int $showId, array $seatIds): array
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if ($seatIds === []) {
            return [];
        }

        if ($this->usesArrayStore()) {
            $owners = [];
            foreach ($seatIds as $seatId) {
                $hold = $this->activeArrayHold($this->key($showId, $seatId));
                if ($hold) {
                    $owners[$seatId] = $hold['owner'];
                }
            }

            return $owners;
        }

        try {
            $keys = array_map(fn (int $seatId): string => $this->key($showId, $seatId), $seatIds);
            $values = Redis::connection($this->connection())->command('mget', [$keys]);
            $owners = [];

            foreach ($seatIds as $index => $seatId) {
                if (($values[$index] ?? null) !== null) {
                    $owners[$seatId] = (string) $values[$index];
                }
            }

            return $owners;
        } catch (Throwable $exception) {
            Log::warning('Could not read Redis seat holds; using relational booking state.', [
                'show_id' => $showId,
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    public function ownerForUser(int $userId): string
    {
        return "user:{$userId}";
    }

    /** @param  list<int>  $seatIds */
    private function acquireInArray(int $showId, array $seatIds, string $owner, int $ttlSeconds): array
    {
        foreach ($seatIds as $seatId) {
            $current = $this->activeArrayHold($this->key($showId, $seatId));
            if ($current && $current['owner'] !== $owner) {
                return ['acquired' => false, 'conflict_seat_id' => $seatId];
            }
        }

        foreach ($seatIds as $seatId) {
            $this->arrayHolds[$this->key($showId, $seatId)] = [
                'owner' => $owner,
                'expires_at' => now()->timestamp + $ttlSeconds,
            ];
        }

        return ['acquired' => true, 'conflict_seat_id' => null];
    }

    /** @return array{owner: string, expires_at: int}|null */
    private function activeArrayHold(string $key): ?array
    {
        $hold = $this->arrayHolds[$key] ?? null;
        if ($hold && $hold['expires_at'] <= now()->timestamp) {
            unset($this->arrayHolds[$key]);

            return null;
        }

        return $hold;
    }

    /** @param  list<int>  $seatIds
     * @return list<int>
     */
    private function normalizeSeatIds(array $seatIds): array
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);

        return $seatIds;
    }

    private function key(int $showId, int $seatId): string
    {
        return "nosql:seat-hold:show:{$showId}:seat:{$seatId}";
    }

    private function usesArrayStore(): bool
    {
        return config('nosql.redis.seat_hold_store') === 'array';
    }

    private function connection(): string
    {
        return (string) config('nosql.redis.connection', 'default');
    }
}
