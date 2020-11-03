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

$prefix = \trim(\parse_url(\config('app.url'), PHP_URL_PATH), '/') . '/';

Route::group(
    [
        'middleware' => 'api',
        'prefix' => $prefix . 'api/auth'
    ],
    function ($router) {
        Route::post('login', 'API\AuthController@login');

        Route::group(
            ['middleware' => 'auth:api'],
            function ($router) {
                Route::get('info', 'API\AuthController@info');
                Route::post('logout', 'API\AuthController@logout');
                Route::post('refresh', 'API\AuthController@refresh');
            }
        );
    }
);

Route::group(
    [
        'domain' => \config('app.domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'api/auth'
    ],
    function ($router) {
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
        'domain' => \config('app.domain'),
        'middleware' => 'auth:api',
        'prefix' => $prefix . 'api/v4'
    ],
    function () {
        Route::apiResource('domains', API\V4\DomainsController::class);
        Route::get('domains/{id}/confirm', 'API\V4\DomainsController@confirm');
        Route::get('domains/{id}/status', 'API\V4\DomainsController@status');

        Route::apiResource('entitlements', API\V4\EntitlementsController::class);
        Route::apiResource('packages', API\V4\PackagesController::class);
        Route::apiResource('skus', API\V4\SkusController::class);
        Route::apiResource('users', API\V4\UsersController::class);
        Route::get('users/{id}/status', 'API\V4\UsersController@status');

        Route::apiResource('wallets', API\V4\WalletsController::class);
        Route::get('wallets/{id}/transactions', 'API\V4\WalletsController@transactions');
        Route::get('wallets/{id}/receipts', 'API\V4\WalletsController@receipts');
        Route::get('wallets/{id}/receipts/{receipt}', 'API\V4\WalletsController@receiptDownload');

        Route::post('payments', 'API\V4\PaymentsController@store');
        Route::get('payments/mandate', 'API\V4\PaymentsController@mandate');
        Route::post('payments/mandate', 'API\V4\PaymentsController@mandateCreate');
        Route::put('payments/mandate', 'API\V4\PaymentsController@mandateUpdate');
        Route::delete('payments/mandate', 'API\V4\PaymentsController@mandateDelete');
    }
);

Route::group(
    [
        'domain' => \config('app.domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'api/v4'
    ],
    function ($router) {
        Route::post('support/request', 'API\V4\SupportController@request');
    }
);

Route::group(
    [
        'domain' => \config('app.domain'),
        'prefix' => $prefix . 'api/webhooks',
    ],
    function () {
        Route::post('payment/{provider}', 'API\V4\PaymentsController@webhook');
    }
);

Route::group(
    [
        'domain' => 'admin.' . \config('app.domain'),
        'middleware' => ['auth:api', 'admin'],
        'prefix' => $prefix . 'api/v4',
    ],
    function () {
        Route::apiResource('domains', API\V4\Admin\DomainsController::class);
        Route::get('domains/{id}/confirm', 'API\V4\Admin\DomainsController@confirm');
        Route::post('domains/{id}/suspend', 'API\V4\Admin\DomainsController@suspend');
        Route::post('domains/{id}/unsuspend', 'API\V4\Admin\DomainsController@unsuspend');

        Route::apiResource('entitlements', API\V4\Admin\EntitlementsController::class);
        Route::apiResource('packages', API\V4\Admin\PackagesController::class);
        Route::apiResource('skus', API\V4\Admin\SkusController::class);
        Route::apiResource('users', API\V4\Admin\UsersController::class);
        Route::post('users/{id}/reset2FA', 'API\V4\Admin\UsersController@reset2FA');
        Route::post('users/{id}/suspend', 'API\V4\Admin\UsersController@suspend');
        Route::post('users/{id}/unsuspend', 'API\V4\Admin\UsersController@unsuspend');
        Route::apiResource('wallets', API\V4\Admin\WalletsController::class);
        Route::post('wallets/{id}/one-off', 'API\V4\Admin\WalletsController@oneOff');
        Route::get('wallets/{id}/transactions', 'API\V4\Admin\WalletsController@transactions');
        Route::apiResource('discounts', API\V4\Admin\DiscountsController::class);
    }
);
