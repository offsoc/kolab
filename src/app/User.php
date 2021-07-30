<?php

namespace App;

use App\Entitlement;
use App\UserAlias;
use App\Sku;
use App\Traits\UserConfigTrait;
use App\Traits\UserAliasesTrait;
use App\Traits\SettingsTrait;
use App\Wallet;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Iatstuti\Database\Support\NullableFields;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * The eloquent definition of a User.
 *
 * @property string $email
 * @property int    $id
 * @property string $password
 * @property int    $status
 * @property int    $tenant_id
 */
class User extends Authenticatable implements JWTSubject
{
    use NullableFields;
    use UserConfigTrait;
    use UserAliasesTrait;
    use SettingsTrait;
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
        'id',
        'email',
        'password',
        'password_ldap',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'password_ldap',
        'role'
    ];

    protected $nullable = [
        'password',
        'password_ldap'
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
                        'fee' => $sku->pivot->fee(),
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
    public function assignSku(Sku $sku, int $count = 1): User
    {
        // TODO: I guess wallet could be parametrized in future
        $wallet = $this->wallet();
        $exists = $this->entitlements()->where('sku_id', $sku->id)->count();

        while ($count > 0) {
            \App\Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => $exists >= $sku->units_free ? $sku->cost : 0,
                'fee' => $exists >= $sku->units_free ? $sku->fee : 0,
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
     * @param mixed $object A user|domain|wallet|group object
     *
     * @return bool True if he can, False otherwise
     */
    public function canDelete($object): bool
    {
        if (!method_exists($object, 'wallet')) {
            return false;
        }

        $wallet = $object->wallet();

        // TODO: For now controller can delete/update the account owner,
        //       this may change in future, controllers are not 0-regression feature

        return $this->wallets->contains($wallet) || $this->accounts->contains($wallet);
    }

    /**
     * Check if current user can read data of another object.
     *
     * @param mixed $object A user|domain|wallet|group object
     *
     * @return bool True if he can, False otherwise
     */
    public function canRead($object): bool
    {
        if ($this->role == 'admin') {
            return true;
        }

        if ($object instanceof User && $this->id == $object->id) {
            return true;
        }

        if ($this->role == 'reseller') {
            if ($object instanceof User && $object->role == 'admin') {
                return false;
            }

            if ($object instanceof Wallet && !empty($object->owner)) {
                $object = $object->owner;
            }

            return isset($object->tenant_id) && $object->tenant_id == $this->tenant_id;
        }

        if ($object instanceof Wallet) {
            return $object->user_id == $this->id || $object->controllers->contains($this);
        }

        if (!method_exists($object, 'wallet')) {
            return false;
        }

        $wallet = $object->wallet();

        return $wallet && ($this->wallets->contains($wallet) || $this->accounts->contains($wallet));
    }

    /**
     * Check if current user can update data of another object.
     *
     * @param mixed $object A user|domain|wallet|group object
     *
     * @return bool True if he can, False otherwise
     */
    public function canUpdate($object): bool
    {
        if ($object instanceof User && $this->id == $object->id) {
            return true;
        }

        if ($this->role == 'admin') {
            return true;
        }

        if ($this->role == 'reseller') {
            if ($object instanceof User && $object->role == 'admin') {
                return false;
            }

            if ($object instanceof Wallet && !empty($object->owner)) {
                $object = $object->owner;
            }

            return isset($object->tenant_id) && $object->tenant_id == $this->tenant_id;
        }

        return $this->canDelete($object);
    }

    /**
     * Return the \App\Domain for this user.
     *
     * @return \App\Domain|null
     */
    public function domain()
    {
        list($local, $domainName) = explode('@', $this->email);

        $domain = \App\Domain::withTrashed()->where('namespace', $domainName)->first();

        return $domain;
    }

    /**
     * List the domains to which this user is entitled.
     * Note: Active public domains are also returned (for the user tenant).
     *
     * @return Domain[] List of Domain objects
     */
    public function domains(): array
    {
        if ($this->tenant_id) {
            $domains = Domain::where('tenant_id', $this->tenant_id);
        } else {
            $domains = Domain::withEnvTenantContext();
        }

        $domains = $domains->whereRaw(sprintf('(type & %s)', Domain::TYPE_PUBLIC))
            ->whereRaw(sprintf('(status & %s)', Domain::STATUS_ACTIVE))
            ->get()
            ->all();

        foreach ($this->wallets as $wallet) {
            $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
            foreach ($entitlements as $entitlement) {
                $domains[] = $entitlement->entitleable;
            }
        }

        foreach ($this->accounts as $wallet) {
            $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
            foreach ($entitlements as $entitlement) {
                $domains[] = $entitlement->entitleable;
            }
        }

        return $domains;
    }

    /**
     * The user entitlement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
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
        return $this->hasMany('App\Entitlement', 'entitleable_id', 'id')
            ->where('entitleable_type', User::class);
    }

    /**
     * Find whether an email address exists as a user (including deleted users).
     *
     * @param string $email       Email address
     * @param bool   $return_user Return User instance instead of boolean
     *
     * @return \App\User|bool True or User model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_user = false)
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $user = self::withTrashed()->where('email', $email)->first();

        if ($user) {
            return $return_user ? $user : true;
        }

        return false;
    }

    /**
     * Helper to find user by email address, whether it is
     * main email address, alias or an external email.
     *
     * If there's more than one alias NULL will be returned.
     *
     * @param string $email    Email address
     * @param bool   $external Search also for an external email
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

        $aliases = UserAlias::where('alias', $email)->get();

        if (count($aliases) == 1) {
            return $aliases->first()->user;
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
     * Return groups controlled by the current user.
     *
     * @param bool $with_accounts Include groups assigned to wallets
     *                            the current user controls but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function groups($with_accounts = true)
    {
        $wallets = $this->wallets()->pluck('id')->all();

        if ($with_accounts) {
            $wallets = array_merge($wallets, $this->accounts()->pluck('wallet_id')->all());
        }

        return Group::select(['groups.*', 'entitlements.wallet_id'])
            ->distinct()
            ->join('entitlements', 'entitlements.entitleable_id', '=', 'groups.id')
            ->whereIn('entitlements.wallet_id', $wallets)
            ->where('entitlements.entitleable_type', Group::class);
    }

    /**
     * Check if user has an entitlement for the specified SKU.
     *
     * @param string $title The SKU title
     *
     * @return bool True if specified SKU entitlement exists
     */
    public function hasSku(string $title): bool
    {
        $sku = Sku::withObjectTenantContext($this)->where('title', $title)->first();

        if (!$sku) {
            return false;
        }

        return $this->entitlements()->where('sku_id', $sku->id)->count() > 0;
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
     * A shortcut to get the user name.
     *
     * @param bool $fallback Return "<aa.name> User" if there's no name
     *
     * @return string Full user name
     */
    public function name(bool $fallback = false): string
    {
        $settings = $this->getSettings(['first_name', 'last_name']);

        $name = trim($settings['first_name'] . ' ' . $settings['last_name']);

        if (empty($name) && $fallback) {
            return trim(\trans('app.siteuser', ['site' => \App\Tenant::getConfig($this->tenant_id, 'app.name')]));
        }

        return $name;
    }

    /**
     * Remove a number of entitlements for the SKU.
     *
     * @param \App\Sku $sku   The SKU
     * @param int      $count The number of entitlements to remove
     *
     * @return User Self
     */
    public function removeSku(Sku $sku, int $count = 1): User
    {
        $entitlements = $this->entitlements()
            ->where('sku_id', $sku->id)
            ->orderBy('cost', 'desc')
            ->orderBy('created_at')
            ->get();

        $entitlements_count = count($entitlements);

        foreach ($entitlements as $entitlement) {
            if ($entitlements_count <= $sku->units_free) {
                continue;
            }

            if ($count > 0) {
                $entitlement->delete();
                $entitlements_count--;
                $count--;
            }
        }

        return $this;
    }

    public function senderPolicyFrameworkWhitelist($clientName)
    {
        $setting = $this->getSetting('spf_whitelist');

        if (!$setting) {
            return false;
        }

        $whitelist = json_decode($setting);

        $matchFound = false;

        foreach ($whitelist as $entry) {
            if (substr($entry, 0, 1) == '/') {
                $match = preg_match($entry, $clientName);

                if ($match) {
                    $matchFound = true;
                }

                continue;
            }

            if (substr($entry, 0, 1) == '.') {
                if (substr($clientName, (-1 * strlen($entry))) == $entry) {
                    $matchFound = true;
                }

                continue;
            }

            if ($entry == $clientName) {
                $matchFound = true;
                continue;
            }
        }

        return $matchFound;
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
     * The tenant for this user account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo('App\Tenant', 'tenant_id', 'id');
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
     * @param bool $with_accounts Include users assigned to wallets
     *                            the current user controls but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function users($with_accounts = true)
    {
        $wallets = $this->wallets()->pluck('id')->all();

        if ($with_accounts) {
            $wallets = array_merge($wallets, $this->accounts()->pluck('wallet_id')->all());
        }

        return $this->select(['users.*', 'entitlements.wallet_id'])
            ->distinct()
            ->leftJoin('entitlements', 'entitlements.entitleable_id', '=', 'users.id')
            ->whereIn('entitlements.wallet_id', $wallets)
            ->where('entitlements.entitleable_type', User::class);
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
     * @return ?\App\Wallet A wallet object
     */
    public function wallet(): ?Wallet
    {
        $entitlement = $this->entitlement()->withTrashed()->orderBy('created_at', 'desc')->first();

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
        $this->setPasswordAttribute($password);
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
