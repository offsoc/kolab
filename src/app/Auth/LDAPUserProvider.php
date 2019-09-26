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
        error_log("retrieve by id {$identifier}");
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
        if ($user->email == $credentials['email']) {
            error_log("Have user email matching submitted email");
            if (!empty($user->password)) {
                error_log("Have password attribute: {$user->password}");
                if (Hash::check($credentials['password'], $user->password)) {
                    // TODO: update last login time
                    // TODO: Update password_ldap if necessary, examine whether writing to
                    // user->password is sufficient?
                    return true;
                } else {
                    // TODO: Log login failure
                    return false;
                }
            } else if (!empty($user->password_ldap)) {
                $hash = '{SSHA512}' . base64_encode(
                    pack('H*', hash('sha512', $credentials['password']))
                );

                if ($hash == $user->password_ldap) {
                    // TODO: update last login time
                    // TODO: Update password if necessary, examine whether writing to
                    // user->password is sufficient?
                    return true;
                } else {
                    // TODO: Log login failure
                    return false;
                }
            } else {
                // TODO: Log login failure for missing password. Try actual LDAP?
                return false;
            }
        }

        return false;
    }
}
