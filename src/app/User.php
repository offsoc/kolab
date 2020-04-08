<?php

namespace App;

use App\Entitlement;
use App\UserAlias;
use App\Traits\UserAliasesTrait;
use App\Traits\UserSettingsTrait;
use App\Wallet;
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
        'role'
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
     * This does not include wallets owned by the user.
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

        $wallet_id = $this->wallets()->first()->id;

        foreach ($package->skus as $sku) {
            for ($i = $sku->pivot->qty; $i > 0; $i--) {
                \App\Entitlement::create(
                    [
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
     * Assign a package plan to a user.
     *
     * @param \App\Plan   $plan   The plan to assign
     * @param \App\Domain $domain Optional domain object
     *
     * @return \App\User Self
     */
    public function assignPlan($plan, $domain = null): User
    {
        $this->setSetting('plan_id', $plan->id);

        foreach ($plan->packages as $package) {
            if ($package->isDomain()) {
                $domain->assignPackage($package, $this);
            } else {
                $this->assignPackage($package);
            }
        }

        return $this;
    }

    /**
     * Assign a Sku to a user.
     *
     * @param \App\Sku $sku   The sku to assign.
     * @param int      $count Count of entitlements to add
     *
     * @return \App\User Self
     * @throws \Exception
     */
    public function assignSku($sku, int $count = 1): User
    {
        // TODO: I guess wallet could be parametrized in future
        $wallet = $this->wallet();
        $exists = $this->entitlements()->where('sku_id', $sku->id)->count();

        // TODO: Sanity check, this probably should be in preReq() on handlers
        //       or in EntitlementObserver
        if ($sku->handler_class::entitleableClass() != User::class) {
            throw new \Exception("Cannot assign non-user SKU ({$sku->title}) to a user");
        }

        while ($count > 0) {
            \App\Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => $sku->units_free >= $exists ? $sku->cost : 0,
                'entitleable_id' => $this->id,
                'entitleable_type' => User::class
            ]);

            $exists++;
            $count--;
        }

        return $this;
    }

    /**
     * Check if current user can delete another object.
     *
     * @param \App\User|\App\Domain $object A user|domain object
     *
     * @return bool True if he can, False otherwise
     */
    public function canDelete($object): bool
    {
        if (!method_exists($object, 'wallet')) {
            return false;
        }

        if ($this->role == "admin") {
            return true;
        }

        $wallet = $object->wallet();

        // TODO: For now controller can delete/update the account owner,
        //       this may change in future, controllers are not 0-regression feature

        return $this->wallets->contains($wallet) || $this->accounts->contains($wallet);
    }

    /**
     * Check if current user can read data of another object.
     *
     * @param \App\User|\App\Domain $object A user|domain object
     *
     * @return bool True if he can, False otherwise
     */
    public function canRead($object): bool
    {
        if (!method_exists($object, 'wallet')) {
            return false;
        }

        if ($this->role == "admin") {
            return true;
        }

        if ($object instanceof User && $this->id == $object->id) {
            return true;
        }

        $wallet = $object->wallet();

        return $this->wallets->contains($wallet) || $this->accounts->contains($wallet);
    }

    /**
     * Check if current user can update data of another object.
     *
     * @param \App\User|\App\Domain $object A user|domain object
     *
     * @return bool True if he can, False otherwise
     */
    public function canUpdate($object): bool
    {
        if (!method_exists($object, 'wallet')) {
            return false;
        }

        if ($this->role == "admin") {
            return true;
        }

        if ($object instanceof User && $this->id == $object->id) {
            return true;
        }

        return $this->canDelete($object);
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

        foreach ($this->wallets as $wallet) {
            $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
            foreach ($entitlements as $entitlement) {
                $domain = $entitlement->entitleable;
                \Log::info("Found domain for {$this->email}: {$domain->namespace} (owned)");
                $domains[] = $domain;
            }
        }

        foreach ($this->accounts as $wallet) {
            $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
            foreach ($entitlements as $entitlement) {
                $domain = $entitlement->entitleable;
                \Log::info("Found domain {$this->email}: {$domain->namespace} (charged)");
                $domains[] = $domain;
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
     * @param string $email    Email address
     * @param bool   $external Search also by an external email
     *
     * @return \App\User User model object if found
     */
    public static function findByEmail(string $email, bool $external = false): ?User
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
     * Suspend this domain.
     *
     * @return void
     */
    public function suspend(): void
    {
        if ($this->isSuspended()) {
            return;
        }

        $this->status |= User::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Unsuspend this domain.
     *
     * @return void
     */
    public function unsuspend(): void
    {
        if (!$this->isSuspended()) {
            return;
        }

        $this->status ^= User::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Return users controlled by the current user.
     *
     * Users assigned to wallets the current user controls or owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function users()
    {
        $wallets = array_merge(
            $this->wallets()->pluck('id')->all(),
            $this->accounts()->pluck('wallet_id')->all()
        );

        return $this->select(['users.*', 'entitlements.wallet_id'])
            ->distinct()
            ->leftJoin('entitlements', 'entitlements.entitleable_id', '=', 'users.id')
            ->whereIn('entitlements.wallet_id', $wallets)
            ->where('entitlements.entitleable_type', 'App\User');
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
     * Returns the wallet by which the user is controlled
     *
     * @return \App\Wallet A wallet object
     */
    public function wallet(): Wallet
    {
        $entitlement = $this->entitlement()->first();

        // TODO: No entitlement should not happen, but in tests we have
        //       such cases, so we fallback to the user's wallet in this case
        return $entitlement ? $entitlement->wallet : $this->wallets()->first();
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
