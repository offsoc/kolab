<?php

namespace App\Http\Middleware;

use Closure;

class RequestLogger
{
    private static $start;

    public function handle($request, Closure $next)
    {
        // FIXME: This is not really a request start, but we can't
        //        use LARAVEL_START constant when working with swoole
        self::$start = microtime(true);

        return $next($request);
    }

    public function terminate($request, $response)
    {
        if (\App::environment('local')) {
            $url = $request->fullUrl();
            $method = $request->getMethod();
            $mem = round(memory_get_peak_usage() / 1024 / 1024, 1);
            $time = microtime(true) - self::$start;

            \Log::debug(sprintf("C: %s %s [%sM]: %.4f sec.", $method, $url, $mem, $time));
        } else {
            $threshold = \config('logging.slow_log');
            if ($threshold && ($time = microtime(true) - self::$start) > $threshold) {
                $url = $request->fullUrl();
                $method = $request->getMethod();
                \Log::warning(sprintf("[STATS] %s %s: %.4f sec.", $method, $url, $time));
            }
        }
    }
}
