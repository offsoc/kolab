<?php

namespace App;

use App\AuthAttempt;
use App\Traits\AliasesTrait;
use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\EmailPropertyTrait;
use App\Traits\UserConfigTrait;
use App\Traits\UuidIntKeyTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
use Dyrynda\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The eloquent definition of a User.
 *
 * @property string  $email
 * @property int     $id
 * @property string  $password
 * @property string  $password_ldap
 * @property ?string $role
 * @property int     $status
 * @property int     $tenant_id
 */
class User extends Authenticatable
{
    use AliasesTrait;
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use EmailPropertyTrait;
    use HasApiTokens;
    use Notifiable;
    use NullableFields;
    use UserConfigTrait;
    use UuidIntKeyTrait;
    use SettingsTrait;
    use SoftDeletes;
    use StatusPropertyTrait;

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
    // user in "limited feature-set" state
    public const STATUS_DEGRADED   = 1 << 6;
    // a restricted user
    public const STATUS_RESTRICTED = 1 << 7;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_RESELLER = 'reseller';

    /** @var int The allowed states for this object used in StatusPropertyTrait */
    private int $allowed_states = self::STATUS_NEW |
        self::STATUS_ACTIVE |
        self::STATUS_SUSPENDED |
        self::STATUS_DELETED |
        self::STATUS_LDAP_READY |
        self::STATUS_IMAP_READY |
        self::STATUS_DEGRADED |
        self::STATUS_RESTRICTED;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'id',
        'email',
        'password',
        'password_ldap',
        'status',
    ];

    /** @var array<int, string> The attributes that should be hidden for arrays */
    protected $hidden = [
        'password',
        'password_ldap',
        'role'
    ];

    /** @var array<int, string> The attributes that can be null */
    protected $nullable = [
        'password',
        'password_ldap',
        'role',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
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
            Wallet::class,      // The foreign object definition
            'user_accounts',    // The table name
            'user_id',          // The local foreign key
            'wallet_id'         // The remote foreign key
        );
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
                if (!$domain) {
                    throw new \Exception("Attempted to assign a domain package without passing a domain.");
                }
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
        if (!is_object($object) || !method_exists($object, 'wallet')) {
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
     * Degrade the user
     *
     * @return void
     */
    public function degrade(): void
    {
        if ($this->isDegraded()) {
            return;
        }

        $this->status |= User::STATUS_DEGRADED;
        $this->save();
    }

    /**
     * List the domains to which this user is entitled.
     *
     * @param bool $with_accounts Include domains assigned to wallets
     *                            the current user controls but not owns.
     * @param bool $with_public   Include active public domains (for the user tenant).
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function domains($with_accounts = true, $with_public = true)
    {
        $domains = $this->entitleables(Domain::class, $with_accounts);

        if ($with_public) {
            $domains->orWhere(function ($query) {
                if (!$this->tenant_id) {
                    $query->where('tenant_id', $this->tenant_id);
                } else {
                    $query->withEnvTenantContext();
                }

                $query->where('domains.type', '&', Domain::TYPE_PUBLIC)
                    ->where('domains.status', '&', Domain::STATUS_ACTIVE);
            });
        }

        return $domains;
    }

    /**
     * Return entitleable objects of a specified type controlled by the current user.
     *
     * @param string $class         Object class
     * @param bool   $with_accounts Include objects assigned to wallets
     *                              the current user controls, but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    private function entitleables(string $class, bool $with_accounts = true)
    {
        $wallets = $this->wallets()->pluck('id')->all();

        if ($with_accounts) {
            $wallets = array_merge($wallets, $this->accounts()->pluck('wallet_id')->all());
        }

        $object = new $class();
        $table = $object->getTable();

        return $object->select("{$table}.*")
            ->whereExists(function ($query) use ($table, $wallets, $class) {
                $query->select(DB::raw(1))
                    ->from('entitlements')
                    ->whereColumn('entitleable_id', "{$table}.id")
                    ->whereIn('entitlements.wallet_id', $wallets)
                    ->where('entitlements.entitleable_type', $class);
            });
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
     * Storage items for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fsItems()
    {
        return $this->hasMany(Fs\Item::class);
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
        return $this->entitleables(Group::class, $with_accounts);
    }

    /**
     * Returns whether this user (or its wallet owner) is degraded.
     *
     * @param bool $owner Check also the wallet owner instead just the user himself
     *
     * @return bool
     */
    public function isDegraded(bool $owner = false): bool
    {
        if ($this->status & self::STATUS_DEGRADED) {
            return true;
        }

        if ($owner && ($wallet = $this->wallet())) {
            return $wallet->owner && $wallet->owner->isDegraded();
        }

        return false;
    }

    /**
     * Returns whether this user is restricted.
     *
     * @return bool
     */
    public function isRestricted(): bool
    {
        return ($this->status & self::STATUS_RESTRICTED) > 0;
    }

    /**
     * Licenses whis user has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function licenses()
    {
        return $this->hasMany(License::class);
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
            return trim(\trans('app.siteuser', ['site' => Tenant::getConfig($this->tenant_id, 'app.name')]));
        }

        return $name;
    }

    /**
     * Old passwords for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function passwords()
    {
        return $this->hasMany(UserPassword::class);
    }

    /**
     * Restrict this user.
     *
     * @return void
     */
    public function restrict(): void
    {
        if ($this->isRestricted()) {
            return;
        }

        $this->status |= User::STATUS_RESTRICTED;
        $this->save();
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
        return $this->entitleables(Resource::class, $with_accounts);
    }

    /**
     * Return rooms controlled by the current user.
     *
     * @param bool $with_accounts Include rooms assigned to wallets
     *                            the current user controls but not owns.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function rooms($with_accounts = true)
    {
        return $this->entitleables(Meet\Room::class, $with_accounts);
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
        return $this->entitleables(SharedFolder::class, $with_accounts);
    }

    /**
     * Return companion apps by the current user.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function companionApps()
    {
        return \App\CompanionApp::where('user_id', $this->id);
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
     * Un-degrade this user.
     *
     * @return void
     */
    public function undegrade(): void
    {
        if (!$this->isDegraded()) {
            return;
        }

        $this->status ^= User::STATUS_DEGRADED;
        $this->save();
    }

    /**
     * Un-restrict this user.
     *
     * @param bool $deep Unrestrict also all users in the account
     *
     * @return void
     */
    public function unrestrict(bool $deep = false): void
    {
        if ($this->isRestricted()) {
            $this->status ^= User::STATUS_RESTRICTED;
            $this->save();
        }

        // Remove the flag from all users in the user's wallets
        if ($deep) {
            $this->wallets->each(function ($wallet) {
                User::whereIn('id', $wallet->entitlements()->select('entitleable_id')
                    ->where('entitleable_type', User::class))
                    ->each(function ($user) {
                        $user->unrestrict();
                    });
            });
        }
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
        return $this->entitleables(User::class, $with_accounts);
    }

    /**
     * Verification codes for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function verificationcodes()
    {
        return $this->hasMany(VerificationCode::class, 'user_id', 'id');
    }

    /**
     * Wallets this user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
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
            $this->attributes['password'] = Hash::make($password);
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
     * User role mutator
     *
     * @param ?string $role The user role
     */
    public function setRoleAttribute($role)
    {
        if ($role !== null && !in_array($role, [self::ROLE_ADMIN, self::ROLE_RESELLER])) {
            throw new \Exception("Invalid role: {$role}");
        }

        $this->attributes['role'] = $role;
    }

    /**
     * Suspend all users/domains/groups in this account.
     */
    public function suspendAccount(): void
    {
        $this->suspend();

        foreach ($this->wallets as $wallet) {
            $wallet->entitlements()->select('entitleable_id', 'entitleable_type')
                ->distinct()
                ->get()
                ->each(function ($entitlement) {
                    if (
                        defined($entitlement->entitleable_type . '::STATUS_SUSPENDED')
                        && $entitlement->entitleable
                    ) {
                        $entitlement->entitleable->suspend();
                    }
                });
        }
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
            // TODO: update last login time
            if ($updatePassword && (empty($this->password) || empty($this->password_ldap))) {
                $this->password = $password;
                $this->save();
            }
        }

        return $authenticated;
    }

    /**
     * Validate request location regarding geo-lockin
     *
     * @param string $ip IP address to check, usually request()->ip()
     *
     * @return bool
     */
    public function validateLocation($ip): bool
    {
        $countryCodes = json_decode($this->getSetting('limit_geo', "[]"));

        if (empty($countryCodes)) {
            return true;
        }

        return in_array(\App\Utils::countryForIP($ip), $countryCodes);
    }

    /**
     * Check if multi factor verification is enabled
     *
     * @return bool
     */
    public function mfaEnabled(): bool
    {
        return \App\CompanionApp::where('user_id', $this->id)
            ->where('mfa_enabled', true)
            ->exists();
    }

    /**
     * Retrieve and authenticate a user
     *
     * @param string  $username The username
     * @param string  $password The password in plain text
     * @param ?string $clientIP The IP address of the client
     *
     * @return array ['user', 'reason', 'errorMessage']
     */
    public static function findAndAuthenticate($username, $password, $clientIP = null, $verifyMFA = true): array
    {
        $error = null;

        if (!$clientIP) {
            $clientIP = request()->ip();
        }

        $user = User::where('email', $username)->first();

        if (!$user) {
            $error = AuthAttempt::REASON_NOTFOUND;
        }

        // Check user password
        if (!$error && !$user->validateCredentials($username, $password)) {
            $error = AuthAttempt::REASON_PASSWORD;
        }

        if ($verifyMFA) {
            // Check user (request) location
            if (!$error && !$user->validateLocation($clientIP)) {
                $error = AuthAttempt::REASON_GEOLOCATION;
            }

            // Check 2FA
            if (!$error) {
                try {
                    (new \App\Auth\SecondFactor($user))->validate(request()->secondfactor);
                } catch (\Exception $e) {
                    $error = AuthAttempt::REASON_2FA_GENERIC;
                    $message = $e->getMessage();
                }
            }

            // Check 2FA - Companion App
            if (!$error && $user->mfaEnabled()) {
                $attempt = AuthAttempt::recordAuthAttempt($user, $clientIP);
                if (!$attempt->waitFor2FA()) {
                    $error = AuthAttempt::REASON_2FA;
                }
            }
        }

        if ($error) {
            if ($user && empty($attempt)) {
                $attempt = AuthAttempt::recordAuthAttempt($user, $clientIP);
                if (!$attempt->isAccepted()) {
                    $attempt->deny($error);
                    $attempt->save();
                    $attempt->notify();
                }
            }

            if ($user) {
                \Log::info("Authentication failed for {$user->email}. Error: $error");
            }

            return ['reason' => $error, 'errorMessage' => $message ?? \trans("auth.error.{$error}")];
        }

        \Log::info("Successful authentication for {$user->email}");

        return ['user' => $user];
    }

    /**
     * Hook for passport
     *
     * @throws \Throwable
     *
     * @return \App\User User model object if found
     */
    public static function findAndValidateForPassport($username, $password): User
    {
        $verifyMFA = true;
        if (request()->scope == "mfa") {
            \Log::info("Not validating MFA because this is a request for an mfa scope.");
            // Don't verify MFA if this is only an mfa token.
            // If we didn't do this, we couldn't pair backup devices.
            $verifyMFA = false;
        }
        $result = self::findAndAuthenticate($username, $password, null, $verifyMFA);

        if (isset($result['reason'])) {
            if ($result['reason'] == AuthAttempt::REASON_2FA_GENERIC) {
                // This results in a json response of {'error': 'secondfactor', 'error_description': '$errorMessage'}
                throw new OAuthServerException($result['errorMessage'], 6, 'secondfactor', 401);
            }

            // TODO: Display specific error message if 2FA via Companion App was expected?

            throw OAuthServerException::invalidCredentials();
        }

        return $result['user'];
    }
}
