<?php

namespace App\Auth;

use App\User;
use Carbon\Carbon;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class LDAPUserProvider extends EloquentUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        return parent::retrieveById($identifier);
    }

    public function retrieveByCredentials(array $credentials)
    {
        $entries = User::where('email', '=', $credentials['email']);

        if ($entries->count() == 1) {
            $user = $entries->select('id', 'email', 'password', 'password_ldap')->first();

            return $user;
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
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
            }
        }

        // TODO: update last login time
        // TODO: Update password if necessary, examine whether writing to
        // user->password is sufficient?
        if ($authenticated) {
            $user->password = $credentials['password'];
            $user->save();
        } else {
            // TODO: Try actual LDAP?
            \Log::info("Authentication failed for {$user->email}");
        }

        return $authenticated;
    }
}
