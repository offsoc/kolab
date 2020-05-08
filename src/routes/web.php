<?php

// These are the routes for the meet application
Route::group(
    [
        'domain' => 'meet.' . \config('app.domain'),
    ],
    function () {
        Route::get("/", 'MeetController@index');
        Route::get("/{id}", 'MeetController@room');
    }
);

// We can handle every URL with the default action because
// we have client-side router (including 404 error handler).
// This way we don't have to define any "deep link" routes here.
Route::fallback(
    function () {
        return view('root')->with('env', \App\Utils::uiEnv());
    }
);
