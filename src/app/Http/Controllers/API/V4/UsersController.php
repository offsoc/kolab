<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Domain;
use App\Group;
use App\Rules\UserEmailDomain;
use App\Rules\UserEmailLocal;
use App\Sku;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    /** @const array List of user setting keys available for modification in UI */
    public const USER_SETTINGS = [
        'billing_address',
        'country',
        'currency',
        'external_email',
        'first_name',
        'last_name',
        'organization',
        'phone',
    ];

    /**
     * On user create it is filled with a user or group object to force-delete
     * before the creation of a new user record is possible.
     *
     * @var \App\User|\App\Group|null
     */
    protected $deleteBeforeCreate;

    /** @var array Common object properties in the API response */
    protected static $objectProps = ['email'];


    /**
     * Delete a user.
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        $user = User::withEnvTenantContext()->find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        // User can't remove himself until he's the controller
        if (!$this->guard()->user()->canDelete($user)) {
            return $this->errorResponse(403);
        }

        $user->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-delete-success'),
        ]);
    }

    /**
     * Listing of users.
     *
     * The user-entitlements billed to the current user wallet(s)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = $this->guard()->user();
        $search = trim(request()->input('search'));
        $page = intval(request()->input('page')) ?: 1;
        $pageSize = 20;
        $hasMore = false;

        $result = $user->users();

        // Search by user email, alias or name
        if (strlen($search) > 0) {
            // thanks to cloning we skip some extra queries in $user->users()
            $allUsers1 = clone $result;
            $allUsers2 = clone $result;

            $result->whereLike('email', $search)
                ->union(
                    $allUsers1->join('user_aliases', 'users.id', '=', 'user_aliases.user_id')
                        ->whereLike('alias', $search)
                )
                ->union(
                    $allUsers2->join('user_settings', 'users.id', '=', 'user_settings.user_id')
                        ->whereLike('value', $search)
                        ->whereIn('key', ['first_name', 'last_name'])
                );
        }

        $result = $result->orderBy('email')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        // Process the result
        $result = $result->map(
            function ($user) {
                return $this->objectToClient($user);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'hasMore' => $hasMore,
        ];

        return response()->json($result);
    }

    /**
     * Set user config.
     *
     * @param int $id The user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setConfig($id)
    {
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        $errors = $user->setConfig(request()->input());

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-setconfig-success'),
        ]);
    }

    /**
     * Display information on the user account specified by $id.
     *
     * @param int $id The account to show information for.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::withEnvTenantContext()->find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $response = $this->userResponse($user);

        $response['skus'] = \App\Entitlement::objectEntitlementsSummary($user);
        $response['config'] = $user->getConfig();

        return response()->json($response);
    }

    /**
     * Fetch user status (and reload setup process)
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        $user = User::withEnvTenantContext()->find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $response = $this->processStateUpdate($user);
        $response = array_merge($response, self::objectState($user));

        return response()->json($response);
    }

    /**
     * User status (extended) information
     *
     * @param \App\User $user User object
     *
     * @return array Status information
     */
    public static function statusInfo(User $user): array
    {
        $process = self::processStateInfo(
            $user,
            [
                'user-new' => true,
                'user-ldap-ready' => $user->isLdapReady(),
                'user-imap-ready' => $user->isImapReady(),
            ]
        );

        // Check if the user is a controller of his wallet
        $isController = $user->canDelete($user);
        $hasCustomDomain = $user->wallet()->entitlements()
            ->where('entitleable_type', Domain::class)
            ->count() > 0;

        // Get user's entitlements titles
        $skus = $user->entitlements()->select('skus.title')
            ->join('skus', 'skus.id', '=', 'entitlements.sku_id')
            ->get()
            ->pluck('title')
            ->sort()
            ->unique()
            ->values()
            ->all();

        $result = [
            'skus' => $skus,
            // TODO: This will change when we enable all users to create domains
            'enableDomains' => $isController && $hasCustomDomain,
            // TODO: Make 'enableDistlists' working for wallet controllers that aren't account owners
            'enableDistlists' => $isController && $hasCustomDomain && in_array('distlist', $skus),
            // TODO: Make 'enableFolders' working for wallet controllers that aren't account owners
            'enableFolders' => $isController && $hasCustomDomain && in_array('beta-shared-folders', $skus),
            // TODO: Make 'enableResources' working for wallet controllers that aren't account owners
            'enableResources' => $isController && $hasCustomDomain && in_array('beta-resources', $skus),
            'enableUsers' => $isController,
            'enableWallets' => $isController,
        ];

        return array_merge($process, $result);
    }

    /**
     * Create a new user record.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        $current_user = $this->guard()->user();
        $owner = $current_user->wallet()->owner;

        if ($owner->id != $current_user->id) {
            return $this->errorResponse(403);
        }

        $this->deleteBeforeCreate = null;

        if ($error_response = $this->validateUserRequest($request, null, $settings)) {
            return $error_response;
        }

        if (empty($request->package) || !($package = \App\Package::withEnvTenantContext()->find($request->package))) {
            $errors = ['package' => \trans('validation.packagerequired')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        if ($package->isDomain()) {
            $errors = ['package' => \trans('validation.packageinvalid')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        DB::beginTransaction();

        // @phpstan-ignore-next-line
        if ($this->deleteBeforeCreate) {
            $this->deleteBeforeCreate->forceDelete();
        }

        // Create user record
        $user = User::create([
                'email' => $request->email,
                'password' => $request->password,
        ]);

        $owner->assignPackage($package, $user);

        if (!empty($settings)) {
            $user->setSettings($settings);
        }

        if (!empty($request->aliases)) {
            $user->setAliases($request->aliases);
        }

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-create-success'),
        ]);
    }

    /**
     * Update user data.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $user = User::withEnvTenantContext()->find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        // TODO: Decide what attributes a user can change on his own profile
        if (!$current_user->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        if ($error_response = $this->validateUserRequest($request, $user, $settings)) {
            return $error_response;
        }

        // Entitlements, only controller can do that
        if ($request->skus !== null && !$current_user->canDelete($user)) {
            return $this->errorResponse(422, "You have no permission to change entitlements");
        }

        DB::beginTransaction();

        $this->updateEntitlements($user, $request->skus);

        if (!empty($settings)) {
            $user->setSettings($settings);
        }

        if (!empty($request->password)) {
            $user->password = $request->password;
            $user->save();
        }

        if (isset($request->aliases)) {
            $user->setAliases($request->aliases);
        }

        // TODO: Make sure that UserUpdate job is created in case of entitlements update
        //       and no password change. So, for example quota change is applied to LDAP
        // TODO: Review use of $user->save() in the above context

        DB::commit();

        $response = [
            'status' => 'success',
            'message' => \trans('app.user-update-success'),
        ];

        // For self-update refresh the statusInfo in the UI
        if ($user->id == $current_user->id) {
            $response['statusInfo'] = self::statusInfo($user);
        }

        return response()->json($response);
    }

    /**
     * Update user entitlements.
     *
     * @param \App\User $user  The user
     * @param array     $rSkus List of SKU IDs requested for the user in the form [id=>qty]
     */
    protected function updateEntitlements(User $user, $rSkus)
    {
        if (!is_array($rSkus)) {
            return;
        }

        // list of skus, [id=>obj]
        $skus = Sku::withEnvTenantContext()->get()->mapWithKeys(
            function ($sku) {
                return [$sku->id => $sku];
            }
        );

        // existing entitlement's SKUs
        $eSkus = [];

        $user->entitlements()->groupBy('sku_id')
            ->selectRaw('count(*) as total, sku_id')->each(
                function ($e) use (&$eSkus) {
                    $eSkus[$e->sku_id] = $e->total;
                }
            );

        foreach ($skus as $skuID => $sku) {
            $e = array_key_exists($skuID, $eSkus) ? $eSkus[$skuID] : 0;
            $r = array_key_exists($skuID, $rSkus) ? $rSkus[$skuID] : 0;

            if ($sku->handler_class == \App\Handlers\Mailbox::class) {
                if ($r != 1) {
                    throw new \Exception("Invalid quantity of mailboxes");
                }
            }

            if ($e > $r) {
                // remove those entitled more than existing
                $user->removeSku($sku, ($e - $r));
            } elseif ($e < $r) {
                // add those requested more than entitled
                $user->assignSku($sku, ($r - $e));
            }
        }
    }

    /**
     * Create a response data array for specified user.
     *
     * @param \App\User $user User object
     *
     * @return array Response data
     */
    public static function userResponse(User $user): array
    {
        $response = self::objectToClient($user, true);

        // Settings
        $response['settings'] = [];
        foreach ($user->settings()->whereIn('key', self::USER_SETTINGS)->get() as $item) {
            $response['settings'][$item->key] = $item->value;
        }

        // Aliases
        $response['aliases'] = [];
        foreach ($user->aliases as $item) {
            $response['aliases'][] = $item->alias;
        }

        // Status info
        $response['statusInfo'] = self::statusInfo($user);

        // Add more info to the wallet object output
        $map_func = function ($wallet) use ($user) {
            $result = $wallet->toArray();

            if ($wallet->discount) {
                $result['discount'] = $wallet->discount->discount;
                $result['discount_description'] = $wallet->discount->description;
            }

            if ($wallet->user_id != $user->id) {
                $result['user_email'] = $wallet->owner->email;
            }

            $provider = \App\Providers\PaymentProvider::factory($wallet);
            $result['provider'] = $provider->name();

            return $result;
        };

        // Information about wallets and accounts for access checks
        $response['wallets'] = $user->wallets->map($map_func)->toArray();
        $response['accounts'] = $user->accounts->map($map_func)->toArray();
        $response['wallet'] = $map_func($user->wallet());

        return $response;
    }

    /**
     * Prepare user statuses for the UI
     *
     * @param \App\User $user User object
     *
     * @return array Statuses array
     */
    protected static function objectState(User $user): array
    {
        return [
            'isImapReady' => $user->isImapReady(),
            'isLdapReady' => $user->isLdapReady(),
            'isSuspended' => $user->isSuspended(),
            'isActive' => $user->isActive(),
            'isDeleted' => $user->isDeleted() || $user->trashed(),
        ];
    }

    /**
     * Validate user input
     *
     * @param \Illuminate\Http\Request $request  The API request.
     * @param \App\User|null           $user     User identifier
     * @param array                    $settings User settings (from the request)
     *
     * @return \Illuminate\Http\JsonResponse|null The error response on error
     */
    protected function validateUserRequest(Request $request, $user, &$settings = [])
    {
        $rules = [
            'external_email' => 'nullable|email',
            'phone' => 'string|nullable|max:64|regex:/^[0-9+() -]+$/',
            'first_name' => 'string|nullable|max:128',
            'last_name' => 'string|nullable|max:128',
            'organization' => 'string|nullable|max:512',
            'billing_address' => 'string|nullable|max:1024',
            'country' => 'string|nullable|alpha|size:2',
            'currency' => 'string|nullable|alpha|size:3',
            'aliases' => 'array|nullable',
        ];

        if (empty($user) || !empty($request->password) || !empty($request->password_confirmation)) {
            $rules['password'] = 'required|min:4|max:2048|confirmed';
        }

        $errors = [];

        // Validate input
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            $errors = $v->errors()->toArray();
        }

        $controller = $user ? $user->wallet()->owner : $this->guard()->user();

        // For new user validate email address
        if (empty($user)) {
            $email = $request->email;

            if (empty($email)) {
                $errors['email'] = \trans('validation.required', ['attribute' => 'email']);
            } elseif ($error = self::validateEmail($email, $controller, $this->deleteBeforeCreate)) {
                $errors['email'] = $error;
            }
        }

        // Validate aliases input
        if (isset($request->aliases)) {
            $aliases = [];
            $existing_aliases = $user ? $user->aliases()->get()->pluck('alias')->toArray() : [];

            foreach ($request->aliases as $idx => $alias) {
                if (is_string($alias) && !empty($alias)) {
                    // Alias cannot be the same as the email address (new user)
                    if (!empty($email) && Str::lower($alias) == Str::lower($email)) {
                        continue;
                    }

                    // validate new aliases
                    if (
                        !in_array($alias, $existing_aliases)
                        && ($error = self::validateAlias($alias, $controller))
                    ) {
                        if (!isset($errors['aliases'])) {
                            $errors['aliases'] = [];
                        }
                        $errors['aliases'][$idx] = $error;
                        continue;
                    }

                    $aliases[] = $alias;
                }
            }

            $request->aliases = $aliases;
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Update user settings
        $settings = $request->only(array_keys($rules));
        unset($settings['password'], $settings['aliases'], $settings['email']);

        return null;
    }

    /**
     * Execute (synchronously) specified step in a user setup process.
     *
     * @param \App\User $user User object
     * @param string    $step Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(User $user, string $step): ?bool
    {
        try {
            if (strpos($step, 'domain-') === 0) {
                list ($local, $domain) = explode('@', $user->email);
                $domain = Domain::where('namespace', $domain)->first();

                return DomainsController::execProcessStep($domain, $step);
            }

            switch ($step) {
                case 'user-ldap-ready':
                    // User not in LDAP, create it
                    $job = new \App\Jobs\User\CreateJob($user->id);
                    $job->handle();

                    $user->refresh();

                    return $user->isLdapReady();

                case 'user-imap-ready':
                    // User not in IMAP? Verify again
                    // Do it synchronously if the imap admin credentials are available
                    // otherwise let the worker do the job
                    if (!\config('imap.admin_password')) {
                        \App\Jobs\User\VerifyJob::dispatch($user->id);

                        return null;
                    }

                    $job = new \App\Jobs\User\VerifyJob($user->id);
                    $job->handle();

                    $user->refresh();

                    return $user->isImapReady();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }

    /**
     * Email address validation for use as a user mailbox (login).
     *
     * @param string                    $email   Email address
     * @param \App\User                 $user    The account owner
     * @param null|\App\User|\App\Group $deleted Filled with an instance of a deleted user or group
     *                                           with the specified email address, if exists
     *
     * @return ?string Error message on validation error
     */
    public static function validateEmail(string $email, \App\User $user, &$deleted = null): ?string
    {
        $deleted = null;

        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        list($login, $domain) = explode('@', Str::lower($email));

        if (strlen($login) === 0 || strlen($domain) === 0) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        // Check if domain exists
        $domain = Domain::withEnvTenantContext()->where('namespace', $domain)->first();

        if (empty($domain)) {
            return \trans('validation.domaininvalid');
        }

        // Validate login part alone
        $v = Validator::make(
            ['email' => $login],
            ['email' => ['required', new UserEmailLocal(!$domain->isPublic())]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // Check if it is one of domains available to the user
        $domains = \collect($user->domains())->pluck('namespace')->all();

        if (!in_array($domain->namespace, $domains)) {
            return \trans('validation.entryexists', ['attribute' => 'domain']);
        }

        // Check if a user with specified address already exists
        if ($existing_user = User::emailExists($email, true)) {
            // If this is a deleted user in the same custom domain
            // we'll force delete him before
            if (!$domain->isPublic() && $existing_user->trashed()) {
                $deleted = $existing_user;
            } else {
                return \trans('validation.entryexists', ['attribute' => 'email']);
            }
        }

        // Check if an alias with specified address already exists.
        if (User::aliasExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        // Check if a group or resource with specified address already exists
        if (
            ($existing = Group::emailExists($email, true))
            || ($existing = \App\Resource::emailExists($email, true))
        ) {
            // If this is a deleted group/resource in the same custom domain
            // we'll force delete it before
            if (!$domain->isPublic() && $existing->trashed()) {
                $deleted = $existing;
            } else {
                return \trans('validation.entryexists', ['attribute' => 'email']);
            }
        }

        return null;
    }

    /**
     * Email address validation for use as an alias.
     *
     * @param string    $email Email address
     * @param \App\User $user  The account owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateAlias(string $email, \App\User $user): ?string
    {
        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => 'alias']);
        }

        list($login, $domain) = explode('@', Str::lower($email));

        if (strlen($login) === 0 || strlen($domain) === 0) {
            return \trans('validation.entryinvalid', ['attribute' => 'alias']);
        }

        // Check if domain exists
        $domain = Domain::withEnvTenantContext()->where('namespace', $domain)->first();

        if (empty($domain)) {
            return \trans('validation.domaininvalid');
        }

        // Validate login part alone
        $v = Validator::make(
            ['alias' => $login],
            ['alias' => ['required', new UserEmailLocal(!$domain->isPublic())]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['alias'][0];
        }

        // Check if it is one of domains available to the user
        $domains = \collect($user->domains())->pluck('namespace')->all();

        if (!in_array($domain->namespace, $domains)) {
            return \trans('validation.entryexists', ['attribute' => 'domain']);
        }

        // Check if a user with specified address already exists
        if ($existing_user = User::emailExists($email, true)) {
            // Allow an alias in a custom domain to an address that was a user before
            if ($domain->isPublic() || !$existing_user->trashed()) {
                return \trans('validation.entryexists', ['attribute' => 'alias']);
            }
        }

        // Check if an alias with specified address already exists
        if (User::aliasExists($email)) {
            // Allow assigning the same alias to a user in the same group account,
            // but only for non-public domains
            if ($domain->isPublic()) {
                return \trans('validation.entryexists', ['attribute' => 'alias']);
            }
        }

        // Check if a group with specified address already exists
        if (Group::emailExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'alias']);
        }

        return null;
    }
}
