<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class Cors
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
        // TODO: Now we allow all, but we should be allowing only meet.domain.tld

        return $next($request)
            ->header('Access-Control-Allow-Origin', '*')
            ->header(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, DELETE, OPTIONS'
            )
            ->header(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, X-Token-Auth, Authorization'
            );
    }
}
