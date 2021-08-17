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
        return $user->validateCredentials($credentials['email'], $credentials['password']);
    }
}
