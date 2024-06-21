<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('204', function () {
    return response()->noContent();
});

// We can handle every URL with the default action because
// we have client-side router (including 404 error handler).
// This way we don't have to define any "deep link" routes here.
Route::group(
    [
        //'domain' => \config('app.website_domain'),
    ],
    function () {
        Route::get('content/page/{page}', Controllers\ContentController::class . '@pageContent')
            ->where('page', '(.*)');
        Route::get('content/faq/{page}', Controllers\ContentController::class . '@faqContent')
            ->where('page', '(.*)');

        Route::fallback(
            function () {
                // Return 404 for requests to the API end-points that do not exist
                if (strpos(request()->path(), 'api/') === 0) {
                    return Controllers\Controller::errorResponse(404);
                }

                $env = \App\Utils::uiEnv();
                return view($env['view'])->with('env', $env);
            }
        );
    }
);

Route::group(
    [
        'prefix' => 'oauth'
    ],
    function () {
        // We manually specify a subset of endpoints from https://github.com/laravel/passport/blob/11.x/routes/web.php
        // after having disabled automatic routes via Passport::ignoreRoutes()
        Route::post('/token', [
            'uses' => '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken',
            'as' => 'token',
            // 'middleware' => 'throttle',
        ]);

        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/tokens', [
                'uses' => '\Laravel\Passport\Http\Controllers\AuthorizedAccessTokenController@forUser',
                'as' => 'tokens.index',
            ]);

            Route::delete('/tokens/{token_id}', [
                'uses' => '\Laravel\Passport\Http\Controllers\AuthorizedAccessTokenController@destroy',
                'as' => 'tokens.destroy',
            ]);
        });
    }
);

Route::group(
    [
        'prefix' => '.well-known'
    ],
    function () {
        Route::get('/mta-sts.txt', [Controllers\WellKnownController::class, "mtaSts"]);
    }
);
