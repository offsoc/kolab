<?php

namespace App;

use App\UserAlias;
use App\Traits\UserAliasesTrait;
use App\Traits\UserSettingsTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Iatstuti\Database\Support\NullableFields;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * The eloquent definition of a User.
 *
 * @property string $email
 * @property int    $id
 * @property string $name
 * @property string $password
 * @property int    $status
 */
class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    use NullableFields;
    use UserAliasesTrait;
    use UserSettingsTrait;
    use SoftDeletes;

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function accounts()
    {
        return $this->belongsToMany(
            'App\Wallet',       // The foreign object definition
            'user_accounts',    // The table name
            'user_id',          // The local foreign key
            'wallet_id'         // The remote foreign key
        );
    }

    /**
     * Email aliases of this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function aliases()
    {
        return $this->hasMany('App\UserAlias', 'user_id');
    }

    /**
     * Assign a package to a user. The user should not have any existing entitlements.
     *
     * @param \App\Package   $package The package to assign.
     * @param \App\User|null $user    Assign the package to another user.
     *
     * @return \App\User
     */
    public function assignPackage($package, $user = null)
    {
        if (!$user) {
            $user = $this;
        }

        $wallet_id = $this->wallets()->get()[0]->id;

        foreach ($package->skus as $sku) {
            for ($i = $sku->pivot->qty; $i > 0; $i--) {
                \App\Entitlement::create(
                    [
                        'owner_id' => $this->id,
                        'wallet_id' => $wallet_id,
                        'sku_id' => $sku->id,
                        'cost' => $sku->pivot->cost(),
                        'entitleable_id' => $user->id,
                        'entitleable_type' => User::class
                    ]
                );
            }
        }

        return $user;
    }

    /**
     * Returns user controlling the current user (or self when it's the account owner)
     *
     * @return \App\User A user object
     */
    public function controller(): User
    {
        // FIXME: This is most likely not the best way to do this
        $entitlement = \App\Entitlement::where([
                'entitleable_id' => $this->id,
                'entitleable_type' => User::class
        ])->first();

        if ($entitlement && $entitlement->owner_id != $this->id) {
            return $entitlement->owner;
        }

        return $this;
    }

    public function assignPlan($plan, $domain = null)
    {
        $this->setSetting('plan_id', $plan->id);

        foreach ($plan->packages as $package) {
            if ($package->isDomain()) {
                $domain->assignPackage($package, $this);
            } else {
                $this->assignPackage($package);
            }
        }
    }

    /**
     * List the domains to which this user is entitled.
     *
     * @return Domain[]
     */
    public function domains()
    {
        $dbdomains = Domain::whereRaw(
            sprintf(
                '(type & %s) AND (status & %s)',
                Domain::TYPE_PUBLIC,
                Domain::STATUS_ACTIVE
            )
        )->get();

        $domains = [];

        foreach ($dbdomains as $dbdomain) {
            $domains[] = $dbdomain;
        }

        $entitlements = Entitlement::where('owner_id', $this->id)->get();

        foreach ($entitlements as $entitlement) {
            if ($entitlement->entitleable instanceof Domain) {
                $domain = $entitlement->entitleable;
                \Log::info("Found domain for {$this->email}: {$domain->namespace} (owned)");
                $domains[] = $domain;
            }
        }

        foreach ($this->accounts as $wallet) {
            foreach ($wallet->entitlements as $entitlement) {
                if ($entitlement->entitleable instanceof Domain) {
                    $domain = $entitlement->entitleable;
                    \Log::info("Found domain {$this->email}: {$domain->namespace} (charged)");
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
     * Note that these are entitlements that apply to the user account, and not entitlements that
     * this user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement', 'entitleable_id', 'id');
    }

    public function addEntitlement($entitlement)
    {
        if (!$this->entitlements->contains($entitlement)) {
            return $this->entitlements()->save($entitlement);
        }
    }

    /**
     * Helper to find user by email address, whether it is
     * main email address, alias or external email
     *
     * @param string $email Email address
     *
     * @return \App\User User model object if found
     */
    public static function findByEmail(string $email): ?User
    {
        if (strpos($email, '@') === false) {
            return null;
        }

        $email = \strtolower($email);

        $user = self::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $alias = UserAlias::where('alias', $email)->first();

        if ($alias) {
            return $alias->user;
        }

        // TODO: External email

        return null;
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
        return ($this->status & self::STATUS_ACTIVE) > 0;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->status & self::STATUS_DELETED) > 0;
    }

    /**
     * Returns whether this (external) domain has been verified
     * to exist in DNS.
     *
     * @return bool
     */
    public function isImapReady(): bool
    {
        return ($this->status & self::STATUS_IMAP_READY) > 0;
    }

    /**
     * Returns whether this user is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return ($this->status & self::STATUS_LDAP_READY) > 0;
    }

    /**
     * Returns whether this user is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return ($this->status & self::STATUS_SUSPENDED) > 0;
    }

    /**
     * Any (additional) properties of this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function settings()
    {
        return $this->hasMany('App\UserSetting', 'user_id');
    }

    /**
     * Verification codes for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function verificationcodes()
    {
        return $this->hasMany('App\VerificationCode', 'user_id', 'id');
    }

    /**
     * Wallets this user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallets()
    {
        return $this->hasMany('App\Wallet');
    }

    /**
     * User password mutator
     *
     * @param string $password The password in plain text.
     *
     * @return void
     */
    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password, [ "rounds" => 12 ]);
            $this->attributes['password_ldap'] = '{SSHA512}' . base64_encode(
                pack('H*', hash('sha512', $password))
            );
        }
    }

    /**
     * User LDAP password mutator
     *
     * @param string $password The password in plain text.
     *
     * @return void
     */
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
