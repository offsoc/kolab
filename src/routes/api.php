<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(
    [
        'middleware' => 'api',
        'prefix' => 'auth'
    ],
    function ($router) {
        Route::get('info', 'API\UsersController@info');
        Route::post('login', 'API\UsersController@login');
        Route::post('logout', 'API\UsersController@logout');
        Route::post('refresh', 'API\UsersController@refresh');
        Route::post('register', 'API\UsersController@register');
    }
);
