<?php

// We can handle every URL with the default action because
// we have client-side router (including 404 error handler).
// This way we don't have to define any "deep link" routes here.
Route::group(
    [
        //'domain' => \config('app.domain'),
    ],
    function () {
        Route::fallback(
            function () {
                $env = \App\Utils::uiEnv();
                return view($env['view'])->with('env', $env);
            }
        );
    }
);
