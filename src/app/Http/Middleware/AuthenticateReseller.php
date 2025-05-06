<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class AuthenticateReseller
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
        $user = auth()->user();

        if (!$user) {
            abort(401, "Unauthorized");
        }

        if ($user->role !== "reseller") {
            abort(403, "Unauthorized");
        }

        return $next($request);
    }
}
