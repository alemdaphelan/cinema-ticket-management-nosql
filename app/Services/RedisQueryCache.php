<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedisQueryCache
{
    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolver
     * @return array{value: TValue, status: 'hit'|'miss'|'bypass'}
     */
    public function remember(string $group, string $key, int $ttlSeconds, Closure $resolver): array
    {
        try {
            $store = $this->store();
            $version = (int) $store->get($this->versionKey($group), 1);
            $cacheKey = "nosql:{$group}:v{$version}:{$key}";

            if ($store->has($cacheKey)) {
                $this->recordMetric($store, $group, 'hit');

                return ['value' => $store->get($cacheKey), 'status' => 'hit'];
            }
        } catch (Throwable $exception) {
            $this->reportFailure('read', $group, $exception);

            return ['value' => $resolver(), 'status' => 'bypass'];
        }

        $value = $resolver();

        try {
            $store->put($cacheKey, $value, $ttlSeconds);
            $this->recordMetric($store, $group, 'miss');

            return ['value' => $value, 'status' => 'miss'];
        } catch (Throwable $exception) {
            $this->reportFailure('write', $group, $exception);

            return ['value' => $value, 'status' => 'bypass'];
        }
    }

    /** @param  list<string>  $groups */
    public function invalidate(array $groups): void
    {
        foreach (array_unique($groups) as $group) {
            try {
                $store = $this->store();
                $versionKey = $this->versionKey($group);

                if (! $store->add($versionKey, 2, now()->addYears(5))) {
                    $store->increment($versionKey);
                }
            } catch (Throwable $exception) {
                $this->reportFailure('invalidate', $group, $exception);
            }
        }
    }

    private function store(): Repository
    {
        return Cache::store((string) config('nosql.redis.cache_store', 'redis'));
    }

    private function versionKey(string $group): string
    {
        return "nosql:cache-version:{$group}";
    }

    private function recordMetric(Repository $store, string $group, string $status): void
    {
        try {
            $key = 'nosql:cache-metrics:'.now()->format('Y-m-d').":{$group}:{$status}";
            $store->add($key, 0, now()->addDays(7));
            $store->increment($key);
        } catch (Throwable) {
            // Cache metrics must never break the request path.
        }
    }

    private function reportFailure(string $operation, string $group, Throwable $exception): void
    {
        Log::warning('Redis query cache operation failed; using the relational source.', [
            'operation' => $operation,
            'group' => $group,
            'exception' => $exception::class,
        ]);
    }
}
