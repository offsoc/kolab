<?php

namespace Database\Seeds;

use Laravel\Passport\Passport;
use Illuminate\Database\Seeder;
use Illuminate\Encryption\Encrypter;
use phpseclib3\Crypt\RSA;

class PassportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This emulates:
     * './artisan passport:keys --force'
     * './artisan passport:client --password --name="Kolab Password Grant Client" --provider=users'
     *
     * @return void
     */
    public function run()
    {
        //First initialize the passport keys
        [$publicKey, $privateKey] = [
            Passport::keyPath('oauth-public.key'),
            Passport::keyPath('oauth-private.key'),
        ];
        $key = RSA::createKey(4096);
        file_put_contents($publicKey, (string) $key->getPublicKey());
        file_put_contents($privateKey, (string) $key);

        $this->writeNewEnvironmentFileWith('PASSPORT_PRIVATE_KEY', 'passport.private_key', $key);
        $this->writeNewEnvironmentFileWith('PASSPORT_PUBLIC_KEY', 'passport.public_key', (string) $key->getPublicKey());

        //Create a password grant client for the webapp
        $secret = $this->generateRandomKey();

        $client = Passport::client()->forceFill([
            'user_id' => null,
            'name' => "Kolab Password Grant Client",
            'secret' => $secret,
            'provider' => 'users',
            'redirect' => 'https://' . \config('app.website_domain'),
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
        ]);
        $client->save();

        $this->writeNewEnvironmentFileWith('PASSPORT_PROXY_OAUTH_CLIENT_ID', 'auth.proxy.client_id', $client->id);
        $this->writeNewEnvironmentFileWith('PASSPORT_PROXY_OAUTH_CLIENT_SECRET', 'auth.proxy.client_secret', $secret);
    }

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return base64_encode(
            Encrypter::generateKey(\config('app.cipher'))
        );
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param  string  $key
     * @param  string  $configKey
     * @param  string  $value
     * @return void
     */
    protected function writeNewEnvironmentFileWith($key, $configKey, $value)
    {
        $path = \app()->environmentFilePath();
        $count = 0;
        $line = "{$key}=\"{$value}\"";
        $result = preg_replace(
            $this->keyReplacementPattern($key, \config($configKey)),
            $line,
            file_get_contents($path),
            -1,
            $count
        );
        //Append if it doesn't exist
        if ($count == 0) {
            $result = $result . "\n$line";
        }
        file_put_contents($path, $result);
    }

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern($key, $value)
    {
        $escaped = preg_quote("={$value}", '/');
        return "/^{$key}{$escaped}/m";
    }
}
