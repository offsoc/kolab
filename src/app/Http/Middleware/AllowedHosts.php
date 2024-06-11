<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class AllowedHosts
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
        $allowedDomains = \config('app.services_allowed_domains');
        if (!in_array(request()->getHost(), $allowedDomains)) {
            \Log::info("Host not allowed " . request()->getHost());
            abort(404);
        }

        return $next($request);
    }
}
