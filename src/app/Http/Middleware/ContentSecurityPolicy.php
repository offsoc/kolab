<?php

namespace App\Http\Middleware;

use Closure;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $headers = [
            'csp' => 'Content-Security-Policy',
            'xfo' => 'X-Frame-Options',
        ];

        $next = $next($request);

        foreach ($headers as $opt => $header) {
            if ($value = \config("app.headers.{$opt}")) {
                $next->headers->set($header, $value);
            }
        }

        return $next;
    }
}
