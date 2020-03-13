<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Domain;
use App\Rules\UserEmailDomain;
use App\Rules\UserEmailLocal;
use App\Sku;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    /**
     * Create a new API\UsersController instance.
     *
     * Ensures that the correct authentication middleware is applied except for /login
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Helper method for other controllers with user auto-logon
     * functionality
     *
     * @param \App\User $user User model object
     */
    public static function logonResponse(User $user)
    {
        $token = auth()->login($user);

        return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard()->factory()->getTTL() * 60,
        ]);
    }

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
        $user = $this->guard()->user();

        $result = $user->users()->orderBy('email')->get();

        return response()->json($result);
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info()
    {
        $user = $this->guard()->user();
        $response = $this->userResponse($user);

        return response()->json($response);
    }

    /**
     * Get a JWT token via given credentials.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'email' => 'required|min:2',
                'password' => 'required|min:4',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            return $this->respondWithToken($token);
        }

        return response()->json(['status' => 'error', 'message' => __('auth.failed')], 401);
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json([
                'status' => 'success',
                'message' => __('auth.logoutsuccess')
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token Respond with this token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json(
            [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60
            ]
        );
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
     * User status (extended) information
     *
     * @param \App\User $user User object
     *
     * @return array Status information
     */
    public static function statusInfo(User $user): array
    {
        $status = 'new';
        $process = [];
        $steps = [
            'user-new' => true,
            'user-ldap-ready' => 'isLdapReady',
            'user-imap-ready' => 'isImapReady',
        ];

        if ($user->isDeleted()) {
            $status = 'deleted';
        } elseif ($user->isSuspended()) {
            $status = 'suspended';
        } elseif ($user->isActive()) {
            $status = 'active';
        }

        list ($local, $domain) = explode('@', $user->email);
        $domain = Domain::where('namespace', $domain)->first();

        // If that is not a public domain, add domain specific steps
        if ($domain && !$domain->isPublic()) {
            $steps['domain-new'] = true;
            $steps['domain-ldap-ready'] = 'isLdapReady';
            $steps['domain-verified'] = 'isVerified';
            $steps['domain-confirmed'] = 'isConfirmed';
        }

        // Create a process check list
        foreach ($steps as $step_name => $func) {
            $object = strpos($step_name, 'user-') === 0 ? $user : $domain;

            $step = [
                'label' => $step_name,
                'title' => __("app.process-{$step_name}"),
                'state' => is_bool($func) ? $func : $object->{$func}(),
            ];

            if ($step_name == 'domain-confirmed' && !$step['state']) {
                $step['link'] = "/domain/{$domain->id}";
            }

            $process[] = $step;
        }

        return [
            'process' => $process,
            'status' => $status,
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

        $user_name = !empty($settings['first_name']) ? $settings['first_name'] : '';
        if (!empty($settings['last_name'])) {
            $user_name .= ' ' . $settings['last_name'];
        }

        DB::beginTransaction();

        // Create user record
        $user = User::create([
                'name' => $user_name,
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
    protected function userResponse(User $user): array
    {
        $response = $user->toArray();

        // Settings
        // TODO: It might be reasonable to limit the list of settings here to these
        // that are safe and are used in the UI
        $response['settings'] = [];
        foreach ($user->settings as $item) {
            $response['settings'][$item->key] = $item->value;
        }

        // Aliases
        $response['aliases'] = [];
        foreach ($user->aliases as $item) {
            $response['aliases'][] = $item->alias;
        }

        // Status info
        $response['statusInfo'] = self::statusInfo($user);

        // Information about wallets and accounts for access checks
        $response['wallets'] = $user->wallets->toArray();
        $response['accounts'] = $user->accounts->toArray();
        $response['wallet'] = $user->wallet()->toArray();

        return $response;
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
            'first_name' => 'string|nullable|max:512',
            'last_name' => 'string|nullable|max:512',
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
            } elseif ($error = self::validateEmail($email, $controller, false)) {
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
                        && ($error = self::validateEmail($alias, $controller, true))
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
     * Email address (login or alias) validation
     *
     * @param string    $email    Email address
     * @param \App\User $user     The account owner
     * @param bool      $is_alias The email is an alias
     *
     * @return string Error message on validation error
     */
    protected static function validateEmail(string $email, User $user, bool $is_alias = false): ?string
    {
        $attribute = $is_alias ? 'alias' : 'email';

        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => $attribute]);
        }

        list($login, $domain) = explode('@', $email);

        // Check if domain exists
        $domain = Domain::where('namespace', Str::lower($domain))->first();

        if (empty($domain)) {
            return \trans('validation.domaininvalid');
        }

        // Validate login part alone
        $v = Validator::make(
            [$attribute => $login],
            [$attribute => ['required', new UserEmailLocal(!$domain->isPublic())]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()[$attribute][0];
        }

        // Check if it is one of domains available to the user
        // TODO: We should have a helper that returns "flat" array with domain names
        //       I guess we could use pluck() somehow
        $domains = array_map(
            function ($domain) {
                return $domain->namespace;
            },
            $user->domains()
        );

        if (!in_array($domain->namespace, $domains)) {
            return \trans('validation.entryexists', ['attribute' => 'domain']);
        }

        // Check if user with specified address already exists
        if (User::findByEmail($email)) {
            return \trans('validation.entryexists', ['attribute' => $attribute]);
        }

        return null;
    }
}
