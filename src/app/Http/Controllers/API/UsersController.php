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
        $user = Auth::user();

        if (!$user) {
            return abort(403);
        }

        // TODO: check whether or not the user is allowed
        // for now, only allow self.
        if ($user->id != $id) {
            return abort(404);
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
//            $steps['domain-verified'] = 'isVerified';
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
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }
}
