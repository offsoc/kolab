<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Iatstuti\Database\Support\NullableFields;
use Tymon\JWTAuth\Contracts\JWTSubject;

use App\Traits\UserSettingsTrait;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    use NullableFields;
    use UserSettingsTrait;

    // change the default primary key type
    public $incrementing = false;
    protected $keyType = 'bigint';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'password_ldap'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'password_ldap', 'remember_token',
    ];

    protected $nullable = [
        'name'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Any wallets on which this user is a controller.
     *
     * @return Wallet[]
     */
    public function accounts()
    {
        return $this->belongsToMany(
            'App\Wallet',       // The foreign object definition
            'user_accounts',    // The table name
            'user_id',        // The local foreign key
            'wallet_id'       // The remote foreign key
        );
    }

    /**
     * Entitlements for this user.
     *
     * @return Entitlement[]
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
    }

    public function addEntitlement($entitlement)
    {
        if (!$this->entitlements()->get()->contains($entitlement)) {
            return $this->entitlements()->save($entitlement);
        }
    }

    public function settings()
    {
        return $this->hasMany('App\UserSetting', 'user_id');
    }

    /**
     * Wallets this user owns.
     *
     * @return Wallet[]
     */
    public function wallets()
    {
        return $this->hasMany('App\Wallet');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password, [ "rounds" => 12 ]);
            $this->attributes['password_ldap'] = '{SSHA512}' . base64_encode(
                pack('H*', hash('sha512', $password))
            );
        }
    }

    public function setPasswordLdapAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password, [ "rounds" => 12 ]);
            $this->attributes['password_ldap'] = '{SSHA512}' . base64_encode(
                pack('H*', hash('sha512', $password))
            );
        }
    }
}
