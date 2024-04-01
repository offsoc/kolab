<?php

namespace App\Http\Middleware;

use Closure;

class AllowedHosts
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param array|string             $hosts
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $allowedDomains = \config('app.services_allowed_domains');
        if (!in_array(request()->getHost(), $allowedDomains)) {
            return abort(404);
        }
        return $next($request);
    }
}
