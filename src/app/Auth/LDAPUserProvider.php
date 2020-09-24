<?php

namespace App\Auth;

use App\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * A user provider that integrates an LDAP deployment.
 */
class LDAPUserProvider extends EloquentUserProvider implements UserProvider
{
    /**
     * Retrieve the user by its credentials (email).
     *
     * @param array $credentials An array containing the email and password.
     *
     * @return User|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $entries = User::where('email', \strtolower($credentials['email']))->get();

        $count = $entries->count();

        if ($count == 1) {
            return $entries->first();
        }

        if ($count > 1) {
            \Log::warning("Multiple entries for {$credentials['email']}");
        } else {
            \Log::warning("No entries for {$credentials['email']}");
        }

        return null;
    }

    /**
     * Validate the credentials for a user.
     *
     * @param Authenticatable $user        The user.
     * @param array           $credentials The credentials.
     *
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $authenticated = false;

        if ($user->email === \strtolower($credentials['email'])) {
            if (!empty($user->password)) {
                if (Hash::check($credentials['password'], $user->password)) {
                    $authenticated = true;
                }
            } elseif (!empty($user->password_ldap)) {
                if (substr($user->password_ldap, 0, 6) == "{SSHA}") {
                    $salt = substr(base64_decode(substr($user->password_ldap, 6)), 20);

                    $hash = '{SSHA}' . base64_encode(
                        sha1($credentials['password'] . $salt, true) . $salt
                    );

                    if ($hash == $user->password_ldap) {
                        $authenticated = true;
                    }
                } elseif (substr($user->password_ldap, 0, 9) == "{SSHA512}") {
                    $salt = substr(base64_decode(substr($user->password_ldap, 9)), 64);

                    $hash = '{SSHA512}' . base64_encode(
                        pack('H*', hash('sha512', $credentials['password'] . $salt)) . $salt
                    );

                    if ($hash == $user->password_ldap) {
                        $authenticated = true;
                    }
                }
            } else {
                \Log::error("Incomplete credentials for {$user->email}");
            }
        }

        if ($authenticated) {
            \Log::info("Successful authentication for {$user->email}");

            // TODO: update last login time
            if (empty($user->password) || empty($user->password_ldap)) {
                $user->password = $credentials['password'];
                $user->save();
            }
        } else {
            // TODO: Try actual LDAP?
            \Log::info("Authentication failed for {$user->email}");
        }

        return $authenticated;
    }
}
