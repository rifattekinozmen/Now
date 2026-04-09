<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Caching concern for Livewire components.
 * Automatically caches computed data and queries.
 */
trait WithComponentCaching
{
    /**
     * Cache computed data for specified TTL
     * Usage: $this->cacheable('dashboard.kpis', 3600, fn() => $this->calculateKpis())
     */
    protected function cacheable(string $key, int $ttl, callable $callback)
    {
        $cacheKey = 'component.' . auth()->id() . '.' . $key;
        
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get cached value or null
     */
    protected function getCached(string $key)
    {
        $cacheKey = 'component.' . auth()->id() . '.' . $key;
        return Cache::get($cacheKey);
    }

    /**
     * Forget cache entry
     */
    protected function forgetCache(string $key): void
    {
        $cacheKey = 'component.' . auth()->id() . '.' . $key;
        Cache::forget($cacheKey);
    }

    /**
     * Forget all user component caches
     */
    protected function forgetAllComponentCaches(): void
    {
        // Note: Redis supports SCAN, file/array don't.
        // For production, implement proper cache tagging.
    }
}
