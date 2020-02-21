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
     * Retrieve the user by its ID.
     *
     * @param string $identifier The unique ID for the user to attempt to retrieve.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        return parent::retrieveById($identifier);
    }

    /**
     * Retrieve the user by its credentials.
     *
     * Please note that this function also validates the password.
     *
     * @param array $credentials An array containing the email and password.
     *
     * @return User|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $entries = User::where('email', '=', $credentials['email']);

        $count = $entries->count();

        if ($count == 1) {
            $user = $entries->select(['id', 'email', 'password', 'password_ldap'])->first();

            if (!$this->validateCredentials($user, $credentials)) {
                return null;
            }

            return $user;
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

        if ($user->email == $credentials['email']) {
            if (!empty($user->password)) {
                if (Hash::check($credentials['password'], $user->password)) {
                    $authenticated = true;
                }
            } elseif (!empty($user->password_ldap)) {
                $hash = '{SSHA512}' . base64_encode(
                    pack('H*', hash('sha512', $credentials['password']))
                );

                if ($hash == $user->password_ldap) {
                    $authenticated = true;
                }
            } else {
                \Log::error("Incomplete credentials for {$user->email}");
            }
        }

        // TODO: update last login time
        // TODO: Update password if necessary, examine whether writing to
        // user->password is sufficient?
        if ($authenticated) {
            \Log::info("Successful authentication for {$user->email}");

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
