<?php

use App\Http\Controllers\API;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
        'prefix' => $prefix . 'auth'
    ],
    function ($router) {
        Route::post('login', API\AuthController::class . '@login');

        Route::group(
            ['middleware' => 'auth:api'],
            function ($router) {
                Route::get('info', API\AuthController::class . '@info');
                Route::post('info', API\AuthController::class . '@info');
                Route::post('logout', API\AuthController::class . '@logout');
                Route::post('refresh', API\AuthController::class . '@refresh');
            }
        );
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'auth'
    ],
    function ($router) {
        Route::post('password-policy/check', API\PasswordPolicyController::class . '@check');

        Route::post('password-reset/init', API\PasswordResetController::class . '@init');
        Route::post('password-reset/verify', API\PasswordResetController::class . '@verify');
        Route::post('password-reset', API\PasswordResetController::class . '@reset');

        Route::post('signup/init', API\SignupController::class . '@init');
        Route::get('signup/invitations/{id}', API\SignupController::class . '@invitation');
        Route::get('signup/plans', API\SignupController::class . '@plans');
        Route::post('signup/verify', API\SignupController::class . '@verify');
        Route::post('signup', API\SignupController::class . '@signup');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'auth:api',
        'prefix' => $prefix . 'v4'
    ],
    function () {
        Route::post('companion/register', API\V4\CompanionAppsController::class . '@register');

        Route::post('auth-attempts/{id}/confirm', API\V4\AuthAttemptsController::class . '@confirm');
        Route::post('auth-attempts/{id}/deny', API\V4\AuthAttemptsController::class . '@deny');
        Route::get('auth-attempts/{id}/details', API\V4\AuthAttemptsController::class . '@details');
        Route::get('auth-attempts', API\V4\AuthAttemptsController::class . '@index');

        Route::apiResource('domains', API\V4\DomainsController::class);
        Route::get('domains/{id}/confirm', API\V4\DomainsController::class . '@confirm');
        Route::get('domains/{id}/skus', API\V4\SkusController::class . '@domainSkus');
        Route::get('domains/{id}/status', API\V4\DomainsController::class . '@status');
        Route::post('domains/{id}/config', API\V4\DomainsController::class . '@setConfig');

        Route::apiResource('groups', API\V4\GroupsController::class);
        Route::get('groups/{id}/status', API\V4\GroupsController::class . '@status');
        Route::post('groups/{id}/config', API\V4\GroupsController::class . '@setConfig');

        Route::apiResource('packages', API\V4\PackagesController::class);

        Route::apiResource('resources', API\V4\ResourcesController::class);
        Route::get('resources/{id}/status', API\V4\ResourcesController::class . '@status');
        Route::post('resources/{id}/config', API\V4\ResourcesController::class . '@setConfig');

        Route::apiResource('shared-folders', API\V4\SharedFoldersController::class);
        Route::get('shared-folders/{id}/status', API\V4\SharedFoldersController::class . '@status');
        Route::post('shared-folders/{id}/config', API\V4\SharedFoldersController::class . '@setConfig');

        Route::apiResource('skus', API\V4\SkusController::class);

        Route::apiResource('users', API\V4\UsersController::class);
        Route::post('users/{id}/config', API\V4\UsersController::class . '@setConfig');
        Route::get('users/{id}/skus', API\V4\SkusController::class . '@userSkus');
        Route::get('users/{id}/status', API\V4\UsersController::class . '@status');

        Route::apiResource('wallets', API\V4\WalletsController::class);
        Route::get('wallets/{id}/transactions', API\V4\WalletsController::class . '@transactions');
        Route::get('wallets/{id}/receipts', API\V4\WalletsController::class . '@receipts');
        Route::get('wallets/{id}/receipts/{receipt}', API\V4\WalletsController::class . '@receiptDownload');

        Route::get('password-policy', API\PasswordPolicyController::class . '@index');
        Route::post('password-reset/code', API\PasswordResetController::class . '@codeCreate');
        Route::delete('password-reset/code/{id}', API\PasswordResetController::class . '@codeDelete');

        Route::post('payments', API\V4\PaymentsController::class . '@store');
        //Route::delete('payments', API\V4\PaymentsController::class . '@cancel');
        Route::get('payments/mandate', API\V4\PaymentsController::class . '@mandate');
        Route::post('payments/mandate', API\V4\PaymentsController::class . '@mandateCreate');
        Route::put('payments/mandate', API\V4\PaymentsController::class . '@mandateUpdate');
        Route::delete('payments/mandate', API\V4\PaymentsController::class . '@mandateDelete');
        Route::get('payments/methods', API\V4\PaymentsController::class . '@paymentMethods');
        Route::get('payments/pending', API\V4\PaymentsController::class . '@payments');
        Route::get('payments/has-pending', API\V4\PaymentsController::class . '@hasPayments');

        Route::get('openvidu/rooms', API\V4\OpenViduController::class . '@index');
        Route::post('openvidu/rooms/{id}/close', API\V4\OpenViduController::class . '@closeRoom');
        Route::post('openvidu/rooms/{id}/config', API\V4\OpenViduController::class . '@setRoomConfig');

        // FIXME: I'm not sure about this one, should we use DELETE request maybe?
        Route::post('openvidu/rooms/{id}/connections/{conn}/dismiss', API\V4\OpenViduController::class . '@dismissConnection');
        Route::put('openvidu/rooms/{id}/connections/{conn}', API\V4\OpenViduController::class . '@updateConnection');
        Route::post('openvidu/rooms/{id}/request/{reqid}/accept', API\V4\OpenViduController::class . '@acceptJoinRequest');
        Route::post('openvidu/rooms/{id}/request/{reqid}/deny', API\V4\OpenViduController::class . '@denyJoinRequest');
    }
);

// Note: In Laravel 7.x we could just use withoutMiddleware() instead of a separate group
Route::group(
    [
        'domain' => \config('app.website_domain'),
        'prefix' => $prefix . 'v4'
    ],
    function () {
        Route::post('openvidu/rooms/{id}', API\V4\OpenViduController::class . '@joinRoom');
        Route::post('openvidu/rooms/{id}/connections', API\V4\OpenViduController::class . '@createConnection');
        // FIXME: I'm not sure about this one, should we use DELETE request maybe?
        Route::post('openvidu/rooms/{id}/connections/{conn}/dismiss', API\V4\OpenViduController::class . '@dismissConnection');
        Route::put('openvidu/rooms/{id}/connections/{conn}', API\V4\OpenViduController::class . '@updateConnection');
        Route::post('openvidu/rooms/{id}/request/{reqid}/accept', API\V4\OpenViduController::class . '@acceptJoinRequest');
        Route::post('openvidu/rooms/{id}/request/{reqid}/deny', API\V4\OpenViduController::class . '@denyJoinRequest');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'middleware' => 'api',
        'prefix' => $prefix . 'v4'
    ],
    function ($router) {
        Route::post('support/request', API\V4\SupportController::class . '@request');
    }
);

Route::group(
    [
        'domain' => \config('app.website_domain'),
        'prefix' => $prefix . 'webhooks'
    ],
    function () {
        Route::post('payment/{provider}', API\V4\PaymentsController::class . '@webhook');
        Route::post('meet/openvidu', API\V4\OpenViduController::class . '@webhook');
    }
);

if (\config('app.with_services')) {
    Route::group(
        [
            'domain' => 'services.' . \config('app.website_domain'),
            'prefix' => $prefix . 'webhooks'
        ],
        function () {
            Route::get('nginx', API\V4\NGINXController::class . '@authenticate');
            Route::get('nginx-httpauth', API\V4\NGINXController::class . '@httpauth');
            Route::post('policy/greylist', API\V4\PolicyController::class . '@greylist');
            Route::post('policy/ratelimit', API\V4\PolicyController::class . '@ratelimit');
            Route::post('policy/spf', API\V4\PolicyController::class . '@senderPolicyFramework');
        }
    );
}

if (\config('app.with_admin')) {
    Route::group(
        [
            'domain' => 'admin.' . \config('app.website_domain'),
            'middleware' => ['auth:api', 'admin'],
            'prefix' => $prefix . 'v4',
        ],
        function () {
            Route::apiResource('domains', API\V4\Admin\DomainsController::class);
            Route::get('domains/{id}/skus', API\V4\Admin\SkusController::class . '@domainSkus');
            Route::post('domains/{id}/suspend', API\V4\Admin\DomainsController::class . '@suspend');
            Route::post('domains/{id}/unsuspend', API\V4\Admin\DomainsController::class . '@unsuspend');

            Route::apiResource('groups', API\V4\Admin\GroupsController::class);
            Route::post('groups/{id}/suspend', API\V4\Admin\GroupsController::class . '@suspend');
            Route::post('groups/{id}/unsuspend', API\V4\Admin\GroupsController::class . '@unsuspend');

            Route::apiResource('resources', API\V4\Admin\ResourcesController::class);
            Route::apiResource('shared-folders', API\V4\Admin\SharedFoldersController::class);
            Route::apiResource('skus', API\V4\Admin\SkusController::class);
            Route::apiResource('users', API\V4\Admin\UsersController::class);
            Route::get('users/{id}/discounts', API\V4\Reseller\DiscountsController::class . '@userDiscounts');
            Route::post('users/{id}/reset2FA', API\V4\Admin\UsersController::class . '@reset2FA');
            Route::get('users/{id}/skus', API\V4\Admin\SkusController::class . '@userSkus');
            Route::post('users/{id}/skus/{sku}', API\V4\Admin\UsersController::class . '@setSku');
            Route::post('users/{id}/suspend', API\V4\Admin\UsersController::class . '@suspend');
            Route::post('users/{id}/unsuspend', API\V4\Admin\UsersController::class . '@unsuspend');
            Route::apiResource('wallets', API\V4\Admin\WalletsController::class);
            Route::post('wallets/{id}/one-off', API\V4\Admin\WalletsController::class . '@oneOff');
            Route::get('wallets/{id}/transactions', API\V4\Admin\WalletsController::class . '@transactions');

            Route::get('stats/chart/{chart}', API\V4\Admin\StatsController::class . '@chart');
        }
    );
}

if (\config('app.with_reseller')) {
    Route::group(
        [
            'domain' => 'reseller.' . \config('app.website_domain'),
            'middleware' => ['auth:api', 'reseller'],
            'prefix' => $prefix . 'v4',
        ],
        function () {
            Route::apiResource('domains', API\V4\Reseller\DomainsController::class);
            Route::get('domains/{id}/skus', API\V4\Reseller\SkusController::class . '@domainSkus');
            Route::post('domains/{id}/suspend', API\V4\Reseller\DomainsController::class . '@suspend');
            Route::post('domains/{id}/unsuspend', API\V4\Reseller\DomainsController::class . '@unsuspend');

            Route::apiResource('groups', API\V4\Reseller\GroupsController::class);
            Route::post('groups/{id}/suspend', API\V4\Reseller\GroupsController::class . '@suspend');
            Route::post('groups/{id}/unsuspend', API\V4\Reseller\GroupsController::class . '@unsuspend');

            Route::apiResource('invitations', API\V4\Reseller\InvitationsController::class);
            Route::post('invitations/{id}/resend', API\V4\Reseller\InvitationsController::class . '@resend');

            Route::post('payments', API\V4\Reseller\PaymentsController::class . '@store');
            Route::get('payments/mandate', API\V4\Reseller\PaymentsController::class . '@mandate');
            Route::post('payments/mandate', API\V4\Reseller\PaymentsController::class . '@mandateCreate');
            Route::put('payments/mandate', API\V4\Reseller\PaymentsController::class . '@mandateUpdate');
            Route::delete('payments/mandate', API\V4\Reseller\PaymentsController::class . '@mandateDelete');
            Route::get('payments/methods', API\V4\Reseller\PaymentsController::class . '@paymentMethods');
            Route::get('payments/pending', API\V4\Reseller\PaymentsController::class . '@payments');
            Route::get('payments/has-pending', API\V4\Reseller\PaymentsController::class . '@hasPayments');

            Route::apiResource('resources', API\V4\Reseller\ResourcesController::class);
            Route::apiResource('shared-folders', API\V4\Reseller\SharedFoldersController::class);
            Route::apiResource('skus', API\V4\Reseller\SkusController::class);
            Route::apiResource('users', API\V4\Reseller\UsersController::class);
            Route::get('users/{id}/discounts', API\V4\Reseller\DiscountsController::class . '@userDiscounts');
            Route::post('users/{id}/reset2FA', API\V4\Reseller\UsersController::class . '@reset2FA');
            Route::get('users/{id}/skus', API\V4\Reseller\SkusController::class . '@userSkus');
            Route::post('users/{id}/skus/{sku}', API\V4\Admin\UsersController::class . '@setSku');
            Route::post('users/{id}/suspend', API\V4\Reseller\UsersController::class . '@suspend');
            Route::post('users/{id}/unsuspend', API\V4\Reseller\UsersController::class . '@unsuspend');
            Route::apiResource('wallets', API\V4\Reseller\WalletsController::class);
            Route::post('wallets/{id}/one-off', API\V4\Reseller\WalletsController::class . '@oneOff');
            Route::get('wallets/{id}/receipts', API\V4\Reseller\WalletsController::class . '@receipts');
            Route::get('wallets/{id}/receipts/{receipt}', API\V4\Reseller\WalletsController::class . '@receiptDownload');
            Route::get('wallets/{id}/transactions', API\V4\Reseller\WalletsController::class . '@transactions');

            Route::get('stats/chart/{chart}', API\V4\Reseller\StatsController::class . '@chart');
        }
    );
}
