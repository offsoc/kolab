<?php

// We can handle every URL with the default action because
// we have client-side router (including 404 error handler).
// This way we don't have to define any "deep link" routes here.
Route::group(
    [
        //'domain' => \config('app.website_domain'),
    ],
    function () {
        Route::get('content/page/{page}', 'ContentController@pageContent')
            ->where('page', '(.*)');
        Route::get('content/faq/{page}', 'ContentController@faqContent')
            ->where('page', '(.*)');

        Route::fallback(
            function () {
                // Return 404 for requests to the API end-points that do not exist
                if (strpos(request()->path(), 'api/') === 0) {
                    return \App\Http\Controllers\Controller::errorResponse(404);
                }

                $env = \App\Utils::uiEnv();
                return view($env['view'])->with('env', $env);
            }
        );
    }
);
