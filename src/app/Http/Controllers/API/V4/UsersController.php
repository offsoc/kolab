<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Domain;
use App\Rules\UserEmailDomain;
use App\Rules\UserEmailLocal;
use App\Sku;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    // List of user settings keys available for modification in UI
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
     * Delete a user.
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        $user = User::find($id);

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
                'message' => __('app.user-delete-success'),
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
        \Log::debug("Regular API");
        $user = $this->guard()->user();

        $result = $user->users()->orderBy('email')->get()->map(function ($user) {
            $data = $user->toArray();
            $data = array_merge($data, self::userStatuses($user));
            return $data;
        });

        return response()->json($result);
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
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $response = $this->userResponse($user);

        // Simplified Entitlement/SKU information,
        // TODO: I agree this format may need to be extended in future
        $response['skus'] = [];
        foreach ($user->entitlements as $ent) {
            $sku = $ent->sku;
            $response['skus'][$sku->id] = [
//                'cost' => $ent->cost,
                'count' => isset($response['skus'][$sku->id]) ? $response['skus'][$sku->id]['count'] + 1 : 1,
            ];
        }

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
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $response = self::statusInfo($user);

        if (!empty(request()->input('refresh'))) {
            $updated = false;
            $last_step = 'none';

            foreach ($response['process'] as $idx => $step) {
                $last_step = $step['label'];

                if (!$step['state']) {
                    if (!$this->execProcessStep($user, $step['label'])) {
                        break;
                    }

                    $updated = true;
                }
            }

            if ($updated) {
                $response = self::statusInfo($user);
            }

            $success = $response['isReady'];
            $suffix = $success ? 'success' : 'error-' . $last_step;

            $response['status'] = $success ? 'success' : 'error';
            $response['message'] = \trans('app.process-' . $suffix);
        }

        $response = array_merge($response, self::userStatuses($user));

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
        $process = [];
        $steps = [
            'user-new' => true,
            'user-ldap-ready' => $user->isLdapReady(),
            'user-imap-ready' => $user->isImapReady(),
        ];

        // Create a process check list
        foreach ($steps as $step_name => $state) {
            $step = [
                'label' => $step_name,
                'title' => \trans("app.process-{$step_name}"),
                'state' => $state,
            ];

            $process[] = $step;
        }

        list ($local, $domain) = explode('@', $user->email);
        $domain = Domain::where('namespace', $domain)->first();

        // If that is not a public domain, add domain specific steps
        if ($domain && !$domain->isPublic()) {
            $domain_status = DomainsController::statusInfo($domain);
            $process = array_merge($process, $domain_status['process']);
        }

        $all = count($process);
        $checked = count(array_filter($process, function ($v) {
                return $v['state'];
        }));

        $state = $all === $checked ? 'done' : 'running';

        // After 180 seconds assume the process is in failed state,
        // this should unlock the Refresh button in the UI
        if ($all !== $checked && $user->created_at->diffInSeconds(Carbon::now()) > 180) {
            $state = 'failed';
        }

        return [
            'process' => $process,
            'processState' => $state,
            'isReady' => $all === $checked,
        ];
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

        if ($error_response = $this->validateUserRequest($request, null, $settings)) {
            return $error_response;
        }

        if (empty($request->package) || !($package = \App\Package::find($request->package))) {
            $errors = ['package' => \trans('validation.packagerequired')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        if ($package->isDomain()) {
            $errors = ['package' => \trans('validation.packageinvalid')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        DB::beginTransaction();

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
                'message' => __('app.user-create-success'),
        ]);
    }

    /**
     * Update user data.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @params string                  $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

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

        return response()->json([
                'status' => 'success',
                'message' => __('app.user-update-success'),
        ]);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }

    /**
     * Update user entitlements.
     *
     * @param \App\User  $user The user
     * @param array|null $skus Set of SKUs for the user
     */
    protected function updateEntitlements(User $user, $skus)
    {
        if (!is_array($skus)) {
            return;
        }

        // Existing SKUs
        // FIXME: Is there really no query builder method to get result indexed
        //        by some column or primary key?
        $all_skus = Sku::all()->mapWithKeys(function ($sku) {
            return [$sku->id => $sku];
        });

        // Existing user entitlements
        // Note: We sort them by cost, so e.g. for storage we get these free first
        $entitlements = $user->entitlements()->orderBy('cost')->get();

        // Go through existing entitlements and remove those no longer needed
        foreach ($entitlements as $ent) {
            $sku_id = $ent->sku_id;

            if (array_key_exists($sku_id, $skus)) {
                // An existing entitlement exists on the requested list
                $skus[$sku_id] -= 1;

                if ($skus[$sku_id] < 0) {
                    $ent->delete();
                }
            } elseif ($all_skus[$sku_id]->handler_class != \App\Handlers\Mailbox::class) {
                // An existing entitlement does not exists on the requested list
                // Never delete 'mailbox' SKU
                $ent->delete();
            }
        }

        // Add missing entitlements
        foreach ($skus as $sku_id => $count) {
            if ($count > 0 && $all_skus->has($sku_id)) {
                $user->assignSku($all_skus[$sku_id], $count);
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
        $response = $user->toArray();

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

        $response = array_merge($response, self::userStatuses($user));

        // Add discount info to wallet object output
        $map_func = function ($wallet) use ($user) {
            $result = $wallet->toArray();

            if ($wallet->discount) {
                $result['discount'] = $wallet->discount->discount;
                $result['discount_description'] = $wallet->discount->description;
            }

            if ($wallet->user_id != $user->id) {
                $result['user_email'] = $wallet->owner->email;
            }

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
    protected static function userStatuses(User $user): array
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
     * @return \Illuminate\Http\JsonResponse The response on error
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
            } elseif ($error = \App\Utils::validateEmail($email, $controller, false)) {
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
                        && ($error = \App\Utils::validateEmail($alias, $controller, true))
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
    }

    /**
     * Execute (synchronously) specified step in a user setup process.
     *
     * @param \App\User $user User object
     * @param string    $step Step identifier (as in self::statusInfo())
     *
     * @return bool True if the execution succeeded, False otherwise
     */
    public static function execProcessStep(User $user, string $step): bool
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
                    $job = new \App\Jobs\UserCreate($user);
                    $job->handle();
                    return $user->isLdapReady();

                case 'user-imap-ready':
                    // User not in IMAP? Verify again
                    $job = new \App\Jobs\UserVerify($user);
                    $job->handle();
                    return $user->isImapReady();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }
}
