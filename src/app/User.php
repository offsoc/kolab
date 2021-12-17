<?php

namespace App;

use App\Entitlement;
use App\UserAlias;
use App\Sku;
use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\UserAliasesTrait;
use App\Traits\UserConfigTrait;
use App\Traits\UuidIntKeyTrait;
use App\Traits\SettingsTrait;
use App\Wallet;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Iatstuti\Database\Support\NullableFields;
use Laravel\Passport\HasApiTokens;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The eloquent definition of a User.
 *
 * @property string $email
 * @property int    $id
 * @property string $password
 * @property int    $status
 * @property int    $tenant_id
 */
class User extends Authenticatable
{
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use HasApiTokens;
    use NullableFields;
    use UserConfigTrait;
    use UserAliasesTrait;
    use UuidIntKeyTrait;
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

        return $user->assignPackageAndWallet($package, $this->wallets()->first());
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

        return $wallet && ($wallet->user_id == $this->id || $this->accounts->contains($wallet));
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

        return $wallet && ($wallet->user_id == $this->id || $this->accounts->contains($wallet));
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
     *
     * @param bool $with_accounts Include domains assigned to wallets
     *                            the current user controls but not owns.
     * @param bool $with_public   Include active public domains (for the user tenant).
     *
     * @return Domain[] List of Domain objects
     */
    public function domains($with_accounts = true, $with_public = true): array
    {
        $domains = [];

        if ($with_public) {
            if ($this->tenant_id) {
                $domains = Domain::where('tenant_id', $this->tenant_id);
            } else {
                $domains = Domain::withEnvTenantContext();
            }

            $domains = $domains->whereRaw(sprintf('(type & %s)', Domain::TYPE_PUBLIC))
                ->whereRaw(sprintf('(status & %s)', Domain::STATUS_ACTIVE))
                ->get()
                ->all();
        }

        foreach ($this->wallets as $wallet) {
            $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
            foreach ($entitlements as $entitlement) {
                $domains[] = $entitlement->entitleable;
            }
        }

        if ($with_accounts) {
            foreach ($this->accounts as $wallet) {
                $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();
                foreach ($entitlements as $entitlement) {
                    $domains[] = $entitlement->entitleable;
                }
            }
        }

        return $domains;
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
     * @return \App\User|null User model object if found
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
     * Return resources controlled by the current user.
     *
     * @param bool $with_accounts Include resources assigned to wallets
     *                            the current user controls but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function resources($with_accounts = true)
    {
        $wallets = $this->wallets()->pluck('id')->all();

        if ($with_accounts) {
            $wallets = array_merge($wallets, $this->accounts()->pluck('wallet_id')->all());
        }

        return \App\Resource::select(['resources.*', 'entitlements.wallet_id'])
            ->distinct()
            ->join('entitlements', 'entitlements.entitleable_id', '=', 'resources.id')
            ->whereIn('entitlements.wallet_id', $wallets)
            ->where('entitlements.entitleable_type', \App\Resource::class);
    }

    /**
     * Return shared folders controlled by the current user.
     *
     * @param bool $with_accounts Include folders assigned to wallets
     *                            the current user controls but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function sharedFolders($with_accounts = true)
    {
        $wallets = $this->wallets()->pluck('id')->all();

        if ($with_accounts) {
            $wallets = array_merge($wallets, $this->accounts()->pluck('wallet_id')->all());
        }

        return \App\SharedFolder::select(['shared_folders.*', 'entitlements.wallet_id'])
            ->distinct()
            ->join('entitlements', 'entitlements.entitleable_id', '=', 'shared_folders.id')
            ->whereIn('entitlements.wallet_id', $wallets)
            ->where('entitlements.entitleable_type', \App\SharedFolder::class);
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

    /**
     * Validate the user credentials
     *
     * @param string $username       The username.
     * @param string $password       The password in plain text.
     * @param bool   $updatePassword Store the password if currently empty
     *
     * @return bool true on success
     */
    public function validateCredentials(string $username, string $password, bool $updatePassword = true): bool
    {
        $authenticated = false;

        if ($this->email === \strtolower($username)) {
            if (!empty($this->password)) {
                if (Hash::check($password, $this->password)) {
                    $authenticated = true;
                }
            } elseif (!empty($this->password_ldap)) {
                if (substr($this->password_ldap, 0, 6) == "{SSHA}") {
                    $salt = substr(base64_decode(substr($this->password_ldap, 6)), 20);

                    $hash = '{SSHA}' . base64_encode(
                        sha1($password . $salt, true) . $salt
                    );

                    if ($hash == $this->password_ldap) {
                        $authenticated = true;
                    }
                } elseif (substr($this->password_ldap, 0, 9) == "{SSHA512}") {
                    $salt = substr(base64_decode(substr($this->password_ldap, 9)), 64);

                    $hash = '{SSHA512}' . base64_encode(
                        pack('H*', hash('sha512', $password . $salt)) . $salt
                    );

                    if ($hash == $this->password_ldap) {
                        $authenticated = true;
                    }
                }
            } else {
                \Log::error("Incomplete credentials for {$this->email}");
            }
        }

        if ($authenticated) {
            \Log::info("Successful authentication for {$this->email}");

            // TODO: update last login time
            if ($updatePassword && (empty($this->password) || empty($this->password_ldap))) {
                $this->password = $password;
                $this->save();
            }
        } else {
            // TODO: Try actual LDAP?
            \Log::info("Authentication failed for {$this->email}");
        }

        return $authenticated;
    }

    /**
     * Retrieve and authenticate a user
     *
     * @param string $username     The username.
     * @param string $password     The password in plain text.
     * @param string $secondFactor The second factor (secondfactor from current request is used as fallback).
     *
     * @return array ['user', 'reason', 'errorMessage']
     */
    public static function findAndAuthenticate($username, $password, $secondFactor = null): ?array
    {
        $user = User::where('email', $username)->first();
        if (!$user) {
            return ['reason' => 'notfound', 'errorMessage' => "User not found."];
        }

        if (!$user->validateCredentials($username, $password)) {
            return ['reason' => 'credentials', 'errorMessage' => "Invalid password."];
        }



        if (!$secondFactor) {
            // Check the request if there is a second factor provided
            // as fallback.
            $secondFactor = request()->secondfactor;
        }

        try {
            (new \App\Auth\SecondFactor($user))->validate($secondFactor);
        } catch (\Exception $e) {
            return ['reason' => 'secondfactor', 'errorMessage' => $e->getMessage()];
        }

        return ['user' => $user];
    }

    /**
     * Hook for passport
     *
     * @throws \Throwable
     *
     * @return \App\User User model object if found
     */
    public function findAndValidateForPassport($username, $password): User
    {
        $result = self::findAndAuthenticate($username, $password);

        if (isset($result['reason'])) {
            if ($result['reason'] == 'secondfactor') {
                // This results in a json response of {'error': 'secondfactor', 'error_description': '$errorMessage'}
                throw new OAuthServerException($result['errorMessage'], 6, 'secondfactor', 401);
            }
            throw OAuthServerException::invalidCredentials();
        }
        return $result['user'];
    }
}
