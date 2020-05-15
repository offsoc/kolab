<?php

namespace App\Http\Middleware;

use Closure;

class RequestLogger
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        if (\App::environment('local')) {
            $url = $request->fullUrl();
            $method = $request->getMethod();
            $time = microtime(true) - LARAVEL_START;
            $mem = round(memory_get_peak_usage() / 1024 / 1024, 1);

            \Log::debug(sprintf("C: %s %s [%sM]: %.4f sec.", $method, $url, $mem, $time));
        }
    }
}
