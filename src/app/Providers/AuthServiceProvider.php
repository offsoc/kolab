<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Hashes all secrets and thus makes them non-recoverable
        /* Passport::hashClientSecrets(); */
        // Only enable routes for access tokens
        Passport::routes(
            function ($router) {
                $router->forAccessTokens();

                // Override the default route to avoid rate-limiting.
                Route::post('/token', [
                    'uses' => 'AccessTokenController@issueToken',
                    'as' => 'passport.token',
                ]);
            }
        );

        Passport::tokensCan([
            'api' => 'Access API',
            'mfa' => 'Access MFA API',
        ]);

        Passport::tokensExpireIn(now()->addMinutes(\config('auth.token_expiry_minutes')));
        Passport::refreshTokensExpireIn(now()->addMinutes(\config('auth.refresh_token_expiry_minutes')));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::useClientModel(\App\Auth\PassportClient::class);
        Passport::tokenModel()::observe(\App\Observers\Passport\TokenObserver::class);
    }
}
