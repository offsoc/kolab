<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

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

            // Pick up config set in Tests\Browser::withConfig
            // This wouldn't technically need to be in a middleware,
            // but this way we ensure it's propagated during the next request.
            if (Cache::has('duskconfig')) {
                $configJson = Cache::get('duskconfig');
                $configValues = json_decode($configJson, true);
                if (!empty($configValues)) {
                    foreach ($configValues as $key => $value) {
                        \config([$key => $value]);
                    }
                }
            }
        }

        return $next($request);
    }
}
