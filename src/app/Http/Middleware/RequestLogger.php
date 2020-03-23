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
        if (\app('env') != 'production') {
            $url = $request->fullUrl();
            $method = $request->getMethod();

            \Log::debug("C: $method $url -> " . var_export($request->bearerToken(), true));
            // On error response this is so noisy that makes the log unusable
            // \Log::debug("S: " . var_export($response->getContent(), true));
        }
    }
}
