<?php

namespace App\Http\Middleware;

use Closure;

class AuthenticateReseller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
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
