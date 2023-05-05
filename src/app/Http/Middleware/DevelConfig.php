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
