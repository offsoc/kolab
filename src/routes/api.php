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

        Route::post('password-reset/init', 'API\PasswordResetController@init');
        Route::post('password-reset/verify', 'API\PasswordResetController@verify');
        Route::post('password-reset', 'API\PasswordResetController@reset');

        Route::get('signup/plans', 'API\SignupController@plans');
        Route::post('signup/init', 'API\SignupController@init');
        Route::post('signup/verify', 'API\SignupController@verify');
        Route::post('signup', 'API\SignupController@signup');
    }
);

Route::group(
    [
        'middleware' => 'auth:api',
        'prefix' => 'v4'
    ],
    function () {
        Route::apiResource('domains', API\DomainsController::class);
        Route::get('domains/{id}/confirm', 'API\DomainsController@confirm');

        Route::apiResource('entitlements', API\EntitlementsController::class);
        Route::apiResource('packages', API\PackagesController::class);
        Route::apiResource('skus', API\SkusController::class);
        Route::apiResource('users', API\UsersController::class);
        Route::apiResource('wallets', API\WalletsController::class);
    }
);
