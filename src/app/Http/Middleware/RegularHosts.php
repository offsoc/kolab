<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class RegularHosts
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $host = request()->getHost();
        foreach (["admin.", "reseller."] as $subdomain) {
            if (str_starts_with($host, $subdomain)) {
                \Log::debug("Only regular hosts allowed: {$host}");
                abort(404);
            }
        }

        return $next($request);
    }
}
