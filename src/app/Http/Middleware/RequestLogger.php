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
        if (env('ENVIRONMENT', 'production') != 'production') {
            $url = $request->fullUrl();
            $method = $request->getMethod();

            error_log("C: $method $url -> " . var_export($request->bearerToken(), true));
            error_log("S: " . var_export($response->getContent(), true));
        }
    }
}
