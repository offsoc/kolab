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
        if (\config('env') != 'production') {
            $url = $request->fullUrl();
            $method = $request->getMethod();

            \Log::debug("C: $method $url -> " . var_export($request->bearerToken(), true));
            \Log::debug("S: " . var_export($response->getContent(), true));
        }
    }
}
