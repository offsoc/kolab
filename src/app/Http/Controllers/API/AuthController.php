<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info()
    {
        $user = Auth::guard()->user();
        $response = V4\UsersController::userResponse($user);

        if (!empty(request()->input('refresh_token'))) {
            // @phpstan-ignore-next-line
            return $this->respondWithToken(Auth::guard()->refresh(), $response);
        }

        return response()->json($response);
    }

    /**
     * Helper method for other controllers with user auto-logon
     * functionality
     *
     * @param \App\User $user User model object
     */
    public static function logonResponse(User $user)
    {
        // @phpstan-ignore-next-line
        $token = Auth::guard()->login($user);

        return self::respondWithToken($token, ['status' => 'success']);
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
        // TODO: Redirect to dashboard if authenticated.
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

        if ($token = Auth::guard()->attempt($credentials)) {
            $sf = new \App\Auth\SecondFactor(Auth::guard()->user());

            if ($response = $sf->requestHandler($request)) {
                return $response;
            }

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
        Auth::guard()->logout();

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
        // @phpstan-ignore-next-line
        return $this->respondWithToken(Auth::guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token    Respond with this token.
     * @param array  $response Additional response data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function respondWithToken($token, array $response = [])
    {
        $response['access_token'] = $token;
        $response['token_type'] = 'bearer';
        // @phpstan-ignore-next-line
        $response['expires_in'] = Auth::guard()->factory()->getTTL() * 60;

        return response()->json($response);
    }
}
