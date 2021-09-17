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
                Route::post('info', 'API\AuthController@info');
                Route::post('logout', 'API\AuthController@logout');
                Route::post('refresh', 'API\AuthController@refresh');
            }
        );
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'api/auth'
    ],
    function ($router) {
        Route::post('password-reset/init', 'API\PasswordResetController@init');
        Route::post('password-reset/verify', 'API\PasswordResetController@verify');
        Route::post('password-reset', 'API\PasswordResetController@reset');

        Route::post('signup/init', 'API\SignupController@init');
        Route::get('signup/invitations/{id}', 'API\SignupController@invitation');
        Route::get('signup/plans', 'API\SignupController@plans');
        Route::post('signup/verify', 'API\SignupController@verify');
        Route::post('signup', 'API\SignupController@signup');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'auth:api',
        'prefix' => $prefix . 'api/v4'
    ],
    function () {
        Route::post('companion/register', 'API\V4\CompanionAppsController@register');

        Route::post('auth-attempts/{id}/confirm', 'API\V4\AuthAttemptsController@confirm');
        Route::post('auth-attempts/{id}/deny', 'API\V4\AuthAttemptsController@deny');
        Route::get('auth-attempts/{id}/details', 'API\V4\AuthAttemptsController@details');
        Route::get('auth-attempts', 'API\V4\AuthAttemptsController@index');

        Route::apiResource('domains', API\V4\DomainsController::class);
        Route::get('domains/{id}/confirm', 'API\V4\DomainsController@confirm');
        Route::get('domains/{id}/status', 'API\V4\DomainsController@status');
        Route::post('domains/{id}/config', 'API\V4\DomainsController@setConfig');

        Route::apiResource('groups', API\V4\GroupsController::class);
        Route::get('groups/{id}/status', 'API\V4\GroupsController@status');

        Route::get('meet/rooms', 'API\V4\MeetController@index');
        Route::post('meet/rooms/{id}/config', 'API\V4\MeetController@setRoomConfig');

        Route::apiResource('packages', API\V4\PackagesController::class);
        Route::apiResource('skus', API\V4\SkusController::class);

        Route::apiResource('users', API\V4\UsersController::class);
        Route::post('users/{id}/config', 'API\V4\UsersController@setConfig');
        Route::get('users/{id}/skus', 'API\V4\SkusController@userSkus');
        Route::get('users/{id}/status', 'API\V4\UsersController@status');

        Route::apiResource('wallets', API\V4\WalletsController::class);
        Route::get('wallets/{id}/transactions', 'API\V4\WalletsController@transactions');
        Route::get('wallets/{id}/receipts', 'API\V4\WalletsController@receipts');
        Route::get('wallets/{id}/receipts/{receipt}', 'API\V4\WalletsController@receiptDownload');

        Route::post('payments', 'API\V4\PaymentsController@store');
        //Route::delete('payments', 'API\V4\PaymentsController@cancel');
        Route::get('payments/mandate', 'API\V4\PaymentsController@mandate');
        Route::post('payments/mandate', 'API\V4\PaymentsController@mandateCreate');
        Route::put('payments/mandate', 'API\V4\PaymentsController@mandateUpdate');
        Route::delete('payments/mandate', 'API\V4\PaymentsController@mandateDelete');
        Route::get('payments/methods', 'API\V4\PaymentsController@paymentMethods');
        Route::get('payments/pending', 'API\V4\PaymentsController@payments');
        Route::get('payments/has-pending', 'API\V4\PaymentsController@hasPayments');
    }
);

// Note: In Laravel 7.x we could just use withoutMiddleware() instead of a separate group
Route::group(
    [
        'domain' => \config('app.website_domain'),
        'prefix' => $prefix . 'api/v4'
    ],
    function () {
        Route::post('meet/rooms/{id}', 'API\V4\MeetController@joinRoom');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'api/v4'
    ],
    function ($router) {
        Route::post('support/request', 'API\V4\SupportController@request');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'prefix' => $prefix . 'api/webhooks'
    ],
    function () {
        Route::post('payment/{provider}', 'API\V4\PaymentsController@webhook');
        Route::post('meet', 'API\V4\MeetController@webhook');
        Route::get('nginx', 'API\NGINXController@authenticate');
    }
);

if (\config('app.with_services')) {
    Route::group(
        [
            'domain' => 'services.' . \config('app.website_domain'),
            'prefix' => $prefix . 'api/webhooks'
        ],
        function () {
            Route::get('nginx', 'API\V4\NGINXController@authenticate');
            Route::post('policy/greylist', 'API\V4\PolicyController@greylist');
            Route::post('policy/ratelimit', 'API\V4\PolicyController@ratelimit');
            Route::post('policy/spf', 'API\V4\PolicyController@senderPolicyFramework');
        }
    );
}

if (\config('app.with_admin')) {
    Route::group(
        [
            'domain' => 'admin.' . \config('app.website_domain'),
            'middleware' => ['auth:api', 'admin'],
            'prefix' => $prefix . 'api/v4',
        ],
        function () {
            Route::apiResource('domains', API\V4\Admin\DomainsController::class);
            Route::post('domains/{id}/suspend', 'API\V4\Admin\DomainsController@suspend');
            Route::post('domains/{id}/unsuspend', 'API\V4\Admin\DomainsController@unsuspend');

            Route::apiResource('groups', API\V4\Admin\GroupsController::class);
            Route::post('groups/{id}/suspend', 'API\V4\Admin\GroupsController@suspend');
            Route::post('groups/{id}/unsuspend', 'API\V4\Admin\GroupsController@unsuspend');

            Route::apiResource('skus', API\V4\Admin\SkusController::class);
            Route::apiResource('users', API\V4\Admin\UsersController::class);
            Route::get('users/{id}/discounts', 'API\V4\Reseller\DiscountsController@userDiscounts');
            Route::post('users/{id}/reset2FA', 'API\V4\Admin\UsersController@reset2FA');
            Route::get('users/{id}/skus', 'API\V4\Admin\SkusController@userSkus');
            Route::post('users/{id}/suspend', 'API\V4\Admin\UsersController@suspend');
            Route::post('users/{id}/unsuspend', 'API\V4\Admin\UsersController@unsuspend');
            Route::apiResource('wallets', API\V4\Admin\WalletsController::class);
            Route::post('wallets/{id}/one-off', 'API\V4\Admin\WalletsController@oneOff');
            Route::get('wallets/{id}/transactions', 'API\V4\Admin\WalletsController@transactions');

            Route::get('stats/chart/{chart}', 'API\V4\Admin\StatsController@chart');
        }
    );
}

if (\config('app.with_reseller')) {
    Route::group(
        [
            'domain' => 'reseller.' . \config('app.website_domain'),
            'middleware' => ['auth:api', 'reseller'],
            'prefix' => $prefix . 'api/v4',
        ],
        function () {
            Route::apiResource('domains', API\V4\Reseller\DomainsController::class);
            Route::post('domains/{id}/suspend', 'API\V4\Reseller\DomainsController@suspend');
            Route::post('domains/{id}/unsuspend', 'API\V4\Reseller\DomainsController@unsuspend');

            Route::apiResource('groups', API\V4\Reseller\GroupsController::class);
            Route::post('groups/{id}/suspend', 'API\V4\Reseller\GroupsController@suspend');
            Route::post('groups/{id}/unsuspend', 'API\V4\Reseller\GroupsController@unsuspend');

            Route::apiResource('invitations', API\V4\Reseller\InvitationsController::class);
            Route::post('invitations/{id}/resend', 'API\V4\Reseller\InvitationsController@resend');

            Route::post('payments', 'API\V4\Reseller\PaymentsController@store');
            Route::get('payments/mandate', 'API\V4\Reseller\PaymentsController@mandate');
            Route::post('payments/mandate', 'API\V4\Reseller\PaymentsController@mandateCreate');
            Route::put('payments/mandate', 'API\V4\Reseller\PaymentsController@mandateUpdate');
            Route::delete('payments/mandate', 'API\V4\Reseller\PaymentsController@mandateDelete');
            Route::get('payments/methods', 'API\V4\Reseller\PaymentsController@paymentMethods');
            Route::get('payments/pending', 'API\V4\Reseller\PaymentsController@payments');
            Route::get('payments/has-pending', 'API\V4\Reseller\PaymentsController@hasPayments');

            Route::apiResource('skus', API\V4\Reseller\SkusController::class);
            Route::apiResource('users', API\V4\Reseller\UsersController::class);
            Route::get('users/{id}/discounts', 'API\V4\Reseller\DiscountsController@userDiscounts');
            Route::post('users/{id}/reset2FA', 'API\V4\Reseller\UsersController@reset2FA');
            Route::get('users/{id}/skus', 'API\V4\Reseller\SkusController@userSkus');
            Route::post('users/{id}/suspend', 'API\V4\Reseller\UsersController@suspend');
            Route::post('users/{id}/unsuspend', 'API\V4\Reseller\UsersController@unsuspend');
            Route::apiResource('wallets', API\V4\Reseller\WalletsController::class);
            Route::post('wallets/{id}/one-off', 'API\V4\Reseller\WalletsController@oneOff');
            Route::get('wallets/{id}/receipts', 'API\V4\Reseller\WalletsController@receipts');
            Route::get('wallets/{id}/receipts/{receipt}', 'API\V4\Reseller\WalletsController@receiptDownload');
            Route::get('wallets/{id}/transactions', 'API\V4\Reseller\WalletsController@transactions');

            Route::get('stats/chart/{chart}', 'API\V4\Reseller\StatsController@chart');
        }
    );
}
