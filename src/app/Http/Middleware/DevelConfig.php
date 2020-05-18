<?php

namespace App\Http\Middleware;

use Closure;

class DevelConfig
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
        // Only in testing/local environment...
        if (\App::environment('local')) {
            // This is the only way I found to change configuration
            // on a running application. We need this to browser-test both
            // Mollie and Stripe providers without .env file modification
            // and artisan restart
            if ($request->getMethod() == 'GET' && isset($request->paymentProvider)) {
                $provider = $request->paymentProvider;
            } else {
                $provider = $request->headers->get('X-TEST-PAYMENT-PROVIDER');
            }

            if (!empty($provider)) {
                \config(['services.payment_provider' => $provider]);
            }
        }

        return $next($request);
    }
}
