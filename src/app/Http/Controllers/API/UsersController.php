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
     * @return \Illuminate\Http\Response
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
        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            return $this->respondWithToken($token);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out']);
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
     * Display the specified resource.
     *
     * @param int $id The account to show information for.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = Auth::user();

        if (!$user) {
            return abort(403);
        }

        $result = false;

        $user->entitlements()->each(
            function ($entitlement) {
                if ($entitlement->user_id == $id) {
                    $result = true;
                }
            }
        );

        if ($user->id == $id) {
            $result = true;
        }

        if (!$result) {
            return abort(404);
        }

        return \App\User::find($id);
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
