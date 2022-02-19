<?php

namespace App\Providers;

use Defuse\Crypto\Key as EncryptionKey;
use Defuse\Crypto\Encoding as EncryptionEncoding;
use League\OAuth2\Server\AuthorizationServer;
use Laravel\Passport\Passport;
use Laravel\Passport\Bridge;

class PassportServiceProvider extends \Laravel\Passport\PassportServiceProvider
{
    /**
     * Make the authorization service instance.
     *
     * @return \League\OAuth2\Server\AuthorizationServer
     */
    public function makeAuthorizationServer()
    {
        return new AuthorizationServer(
            $this->app->make(Bridge\ClientRepository::class),
            $this->app->make(Bridge\AccessTokenRepository::class),
            $this->app->make(Bridge\ScopeRepository::class),
            $this->makeCryptKey('private'),
            $this->makeEncryptionKey(app('encrypter')->getKey())
        );
    }


    /**
     * Create a Key instance for encrypting the refresh token
     *
     * Based on https://github.com/laravel/passport/pull/820
     *
     * @param string $keyBytes
     * @return \Defuse\Crypto\Key
     */
    private function makeEncryptionKey($keyBytes)
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
