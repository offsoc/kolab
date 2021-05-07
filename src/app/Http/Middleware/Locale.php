<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $langDir = resource_path('lang');
        $enabledLanguages = \App\Http\Controllers\ContentController::locales();
        $default = config('app.locale');
        $lang = null;

        // Try to get the language from the cookie
        if (
            ($cookie = $request->cookie('language'))
            && in_array($cookie, $enabledLanguages)
            && ($cookie == $default || file_exists("$langDir/$cookie"))
        ) {
            $lang = $cookie;
        }

        // If there's no cookie select try the browser languages
        if (!$lang) {
            $preferences = array_map(
                function ($lang) {
                    return preg_replace('/[^a-z].*$/', '', strtolower($lang));
                },
                $request->getLanguages()
            );

            foreach ($preferences as $pref) {
                if (
                    !empty($pref)
                    && in_array($pref, $enabledLanguages)
                    && ($pref == $default || file_exists("$langDir/$pref"))
                ) {
                    $lang = $pref;
                    break;
                }
            }
        }

        if ($lang != $default) {
            app()->setLocale($lang);
        }

        return $next($request);
    }
}