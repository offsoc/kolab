<?php

namespace App\Auth;

use Carbon\Carbon;

class Utils
{
    /**
     * Create a simple authentication token
     *
     * @param string $userid User identifier
     * @param int    $ttl    Token's time to live (in seconds)
     *
     * @return string|null Encrypted token, Null on failure
     */
    public static function tokenCreate($userid, $ttl = 10): ?string
    {
        // Note: Laravel's Crypt::encryptString() creates output that is too long
        // We need output string to be max. 127 characters. For that reason
        // we use a custom implementation, and we use user ID instead of login.

        $cipher = strtolower(config('app.cipher'));
        $key = config('app.key');
        $iv = random_bytes(openssl_cipher_iv_length($cipher));

        $data = $userid . '!' . now()->addSeconds($ttl)->format('YmdHis');

        $value = openssl_encrypt($data, $cipher, $key, 0, $iv, $tag);

        if ($value === false) {
            return null;
        }

        return trim(base64_encode($iv), '=')
            . '!'
            . trim(base64_encode($tag), '=')
            . '!'
            . trim(base64_encode($value), '=');
    }

    /**
     * Vaidate a simple authentication token
     *
     * @param string $token Token
     *
     * @return string|null User identifier, Null on failure
     */
    public static function tokenValidate($token): ?string
    {
        if (!preg_match('|^[a-zA-Z0-9!+/]{50,}$|', $token)) {
            // this isn't a token, probably a normal password
            return null;
        }

        [$iv, $tag, $payload] = explode('!', $token);

        $iv = base64_decode($iv);
        $tag = base64_decode($tag);
        $payload = base64_decode($payload);

        $cipher = strtolower(config('app.cipher'));
        $key = config('app.key');

        $decrypted = openssl_decrypt($payload, $cipher, $key, 0, $iv, $tag);

        if ($decrypted === false) {
            return null;
        }

        $payload = explode('!', $decrypted);

        if (
            count($payload) != 2
            || !preg_match('|^[0-9]+$|', $payload[0])
            || !preg_match('|^[0-9]{14}+$|', $payload[1])
        ) {
            // Invalid payload format
            return null;
        }

        // Check expiration date
        try {
            $expiry = Carbon::create(
                (int) substr($payload[1], 0, 4),
                (int) substr($payload[1], 4, 2),
                (int) substr($payload[1], 6, 2),
                (int) substr($payload[1], 8, 2),
                (int) substr($payload[1], 10, 2),
                (int) substr($payload[1], 12, 2)
            );

            if (now() > $expiry) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        return $payload[0];
    }
}
