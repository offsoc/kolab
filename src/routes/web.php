<?php

// We can handle every URL with the default action because
// we have client-side router (including 404 error handler).
// This way we don't have to define any "deep link" routes here.
Route::group(
    [
        //'domain' => \config('app.domain'),
    ],
    function () {
        Route::get('content/page/{page}', 'ContentController@pageContent')
            ->where('page', '(.*)');
        Route::get('content/faq/{page}', 'ContentController@faqContent')
            ->where('page', '(.*)');

        Route::fallback(
            function () {
                $env = \App\Utils::uiEnv();
                return view($env['view'])->with('env', $env);
            }
        );
    }
);
