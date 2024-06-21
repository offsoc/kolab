<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string|null
     */
    protected function redirectTo($request): ?string
    {
        // We might want to redirect the user to the login route,
        // however, I think we should not, as we're using API routes only.
        // Unauthenticated state response is handled in app/Exceptions/Handler.php

        // if (! $request->expectsJson()) {
        //     return route('login');
        // }

        return null;
    }
}
