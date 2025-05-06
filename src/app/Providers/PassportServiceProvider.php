<?php

namespace App\Providers;

use App\Auth\PassportClient;
use App\Observers\Passport\TokenObserver;
use Defuse\Crypto\Encoding as EncryptionEncoding;
use Defuse\Crypto\Key;
use Defuse\Crypto\Key as EncryptionKey;
use Laravel\Passport\Passport;
use OpenIDConnect\Laravel\PassportServiceProvider as ServiceProvider;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        parent::boot();

        // Passport::ignoreRoutes() is in the AppServiceProvider
        Passport::enablePasswordGrant();

        $scopes = [
            'api' => 'Access API',
            'mfa' => 'Access MFA API',
            'fs' => 'Access Files API',
        ];

        Passport::tokensCan(array_merge($scopes, \config('openid.passport.tokens_can')));

        Passport::tokensExpireIn(now()->addMinutes(\config('auth.token_expiry_minutes')));
        Passport::refreshTokensExpireIn(now()->addMinutes(\config('auth.refresh_token_expiry_minutes')));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::useClientModel(PassportClient::class);
        Passport::tokenModel()::observe(TokenObserver::class);
    }

    /**
     * Create a Key instance for encrypting the refresh token
     *
     * Based on https://github.com/laravel/passport/pull/820
     *
     * @param string $keyBytes
     *
     * @return Key|string
     */
    protected function getEncryptionKey($keyBytes)
    {
        // First, we will encode Laravel's encryption key into a format that the Defuse\Crypto\Key class can use,
        // so we can instantiate a new Key object. We need to do this as the Key class has a private constructor method
        // which means we cannot directly instantiate the class based on our Laravel encryption key.
        $encryptionKeyAscii = EncryptionEncoding::saveBytesToChecksummedAsciiSafeString(
            EncryptionKey::KEY_CURRENT_VERSION,
            $keyBytes
        );

        // Instantiate a Key object so we can take advantage of significantly faster encryption/decryption
        // from https://github.com/thephpleague/oauth2-server/pull/814. The improvement is 200x-300x faster.
        return EncryptionKey::loadFromAsciiSafeString($encryptionKeyAscii);
    }
}
