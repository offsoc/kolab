<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Iatstuti\Database\Support\NullableFields;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Traits\UserSettingsTrait;

/**
 * The eloquent definition of a User.
 */
class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    use NullableFields;
    use UserSettingsTrait;

    // a new user, default on creation
    public const STATUS_NEW        = 1 << 0;
    // it's been activated
    public const STATUS_ACTIVE     = 1 << 1;
    // user has been suspended
    public const STATUS_SUSPENDED  = 1 << 2;
    // user has been deleted
    public const STATUS_DELETED    = 1 << 3;
    // user has been created in LDAP
    public const STATUS_LDAP_READY = 1 << 4;
    // user mailbox has been created in IMAP
    public const STATUS_IMAP_READY = 1 << 5;


    // change the default primary key type
    public $incrementing = false;
    protected $keyType = 'bigint';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_ldap',
        'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'password_ldap',
        'remember_token',
    ];

    protected $nullable = [
        'name',
        'password',
        'password_ldap'
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
     * List the domains to which this user is entitled.
     *
     * @return Domain[]
     */
    public function domains()
    {
        $domains = Domain::whereRaw(
            sprintf(
                '(type & %s) AND (status & %s)',
                Domain::TYPE_PUBLIC,
                Domain::STATUS_ACTIVE
            )
        )->get();

        foreach ($this->entitlements()->get() as $entitlement) {
            if ($entitlement->entitleable instanceof Domain) {
                $domain = Domain::find($entitlement->entitleable_id);
                \Log::info("Found domain {$domain->namespace}");
                $domains[] = $domain;
            }
        }

        foreach ($this->accounts()->get() as $wallet) {
            foreach ($wallet->entitlements()->get() as $entitlement) {
                if ($entitlement->entitleable instanceof Domain) {
                    $domain = Domain::find($entitlement->entitleable_id);
                    \Log::info("Found domain {$domain->namespace}");
                    $domains[] = $domain;
                }
            }
        }

        return $domains;
    }

    public function entitlement()
    {
        return $this->morphOne('App\Entitlement', 'entitleable');
    }

    /**
     * Entitlements for this user.
     *
     * @return Entitlement[]
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement', 'owner_id', 'id');
    }

    public function addEntitlement($entitlement)
    {
        // FIXME: This contains() check looks fishy
        if (!$this->entitlements()->get()->contains($entitlement)) {
            return $this->entitlements()->save($entitlement);
        }
    }

    /**
     * Helper to find user by email address, whether it is
     * main email address, alias or external email
     *
     * @param string $email Email address
     *
     * @return \App\User User model object
     */
    public static function findByEmail(string $email): ?User
    {
        if (strpos($email, '@') === false) {
            return null;
        }

        $user = self::where('email', $email)->first();

        // TODO: Aliases, External email

        return $user;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Returns whether this domain is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status & self::STATUS_ACTIVE;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->status & self::STATUS_DELETED;
    }

    /**
     * Returns whether this (external) domain has been verified
     * to exist in DNS.
     *
     * @return bool
     */
    public function isImapReady(): bool
    {
        return $this->status & self::STATUS_IMAP_READY;
    }

    /**
     * Returns whether this user is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return $this->status & self::STATUS_LDAP_READY;
    }

    /**
     * Returns whether this user is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->status & self::STATUS_NEW;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status & self::STATUS_SUSPENDED;
    }

    /**
     * Any (additional) properties of this user.
     *
     * @return \App\UserSetting[]
     */
    public function settings()
    {
        return $this->hasMany('App\UserSetting', 'user_id');
    }

    /**
     * Verification codes for this user.
     *
     * @return VerificationCode[]
     */
    public function verificationcodes()
    {
        return $this->hasMany('App\VerificationCode', 'user_id', 'id');
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

    /**
     * User status mutator
     *
     * @throws \Exception
     */
    public function setStatusAttribute($status)
    {
        $new_status = 0;

        $allowed_values = [
            self::STATUS_NEW,
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_DELETED,
            self::STATUS_LDAP_READY,
            self::STATUS_IMAP_READY,
        ];

        foreach ($allowed_values as $value) {
            if ($status & $value) {
                $new_status |= $value;
                $status ^= $value;
            }
        }

        if ($status > 0) {
            throw new \Exception("Invalid user status: {$status}");
        }

        $this->attributes['status'] = $new_status;
    }
}
