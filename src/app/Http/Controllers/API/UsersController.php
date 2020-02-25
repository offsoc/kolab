<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Domain;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
     * Display a listing of the resources.
     *
     * The user themself, and other user entitlements.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $result = [$user];

        $user->entitlements()->each(
            function ($entitlement) {
                $result[] = User::find($entitlement->user_id);
            }
        );

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
        $response = $user->toArray();

        // Settings
        // TODO: It might be reasonable to limit the list of settings here to these
        // that are safe and are used in the UI
        $response['settings'] = [];
        foreach ($user->settings as $item) {
            $response['settings'][$item->key] = $item->value;
        }

        // Status info
        $response['statusInfo'] = self::statusInfo($user);

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
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function show($id)
    {
        if (!$this->hasAccess($id)) {
            return $this->errorResponse(403);
        }

        $user = User::find($id);

        if (empty($user)) {
            return  $this->errorResponse(404);
        }

        return response()->json($user);
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
        if (!$domain->isPublic()) {
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
        // TODO
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
        if (!$this->hasAccess($id)) {
            return $this->errorResponse(403);
        }

        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        $rules = [
            'external_email' => 'nullable|email',
            'phone' => 'string|nullable|max:64|regex:/^[0-9+() -]+$/',
            'first_name' => 'string|nullable|max:512',
            'last_name' => 'string|nullable|max:512',
            'billing_address' => 'string|nullable|max:1024',
            'country' => 'string|nullable|alpha|size:2',
            'currency' => 'string|nullable|alpha|size:3',
        ];

        if (!empty($request->password) || !empty($request->password_confirmation)) {
            $rules['password'] = 'required|min:4|max:2048|confirmed';
        }

        // Validate input
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Update user settings
        $settings = $request->only(array_keys($rules));
        unset($settings['password']);

        if (!empty($settings)) {
            $user->setSettings($settings);
        }

        // Update user password
        if (!empty($rules['password'])) {
            $user->password = $request->password;
            $user->save();
        }

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
     * Check if the current user has access to the specified user
     *
     * @param int $user_id User identifier
     *
     * @return bool True if current user has access, False otherwise
     */
    protected function hasAccess($user_id): bool
    {
        $current_user = $this->guard()->user();

        // TODO: Admins, other users
        // FIXME: This probably should be some kind of middleware/guard

        return $current_user->id == $user_id;
    }
}
