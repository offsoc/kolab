<?php

namespace App\SPF;

use Illuminate\Support\Facades\Cache as LaravelCache;

/**
 * A caching layer for SPF check results, as sometimes the chasing of DNS entries can take a while but submissions
 * inbound are virtually not rate-limited.
 *
 * A cache key should have the format of ip(4|6)_id_domain and last for 12 hours.
 *
 * A cache value should have a serialized version of the \SPFLib\Checker.
 */
class Cache
{
    public static function get($key)
    {
        if (LaravelCache::has($key)) {
            return LaravelCache::get($key);
        }

        return null;
    }

    public static function has($key)
    {
        return LaravelCache::has($key);
    }

    public static function set($key, $value)
    {
        if (LaravelCache::has($key)) {
            LaravelCache::forget($key);
        }

        // cache the DNS record result for 12 hours
        LaravelCache::put($key, $value, 60 * 60 * 12);
    }
}
