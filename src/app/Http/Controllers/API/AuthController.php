<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;

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

        if (!empty(request()->input('refresh'))) {
            return $this->refreshAndRespond(request(), $response);
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
        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'username' => $user->email,
            'password' => $user->password,
            'grant_type' => 'password',
            'client_id' => config('auth.proxy.client_id'),
            'client_secret' => config('auth.proxy.client_secret'),
            'scopes' => '[*]'
        ]);

        $tokenResponse = app()->handle($proxyRequest);

        $response = V4\UsersController::userResponse($user);
        $response['status'] = 'success';

        return self::respondWithToken($tokenResponse, $response);
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

        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'username' => $request->email,
            'password' => $request->password,
            'grant_type' => 'password',
            'client_id' => config('auth.proxy.client_id'),
            'client_secret' => config('auth.proxy.client_secret'),
            'scopes' => '[*]'
        ]);

        $tokenResponse = app()->handle($proxyRequest);

        if ($tokenResponse->getStatusCode() === 200) {
            $user = \App\User::where('email', $request->email)->first();
            if (!$user) {
                throw new  \Exception("Authentication required.");
            }

            $sf = new \App\Auth\SecondFactor($user);

            // Returns null on success
            if ($response = $sf->requestHandler($request)) {
                return $response;
            }

            $response = V4\UsersController::userResponse($user);

            return $this->respondWithToken($tokenResponse, $response);
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
        $tokenId = Auth::user()->token()->id;

        $tokenRepository = app(TokenRepository::class);
        $refreshTokenRepository = app(RefreshTokenRepository::class);

        // Revoke an access token...
        $tokenRepository->revokeAccessToken($tokenId);

        // Revoke all of the token's refresh tokens...
        $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);
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
    public function refresh(Request $request)
    {
        return self::refreshAndRespond($request);
    }


    protected static function refreshAndRespond($request, array $response = [])
    {
        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => config('auth.proxy.client_id'),
            'client_secret' => config('auth.proxy.client_secret'),
        ]);

        $tokenResponse = app()->handle($proxyRequest);

        return self::respondWithToken($tokenResponse, $response);
    }

    /**
     * Get the token array structure.
     *
     * @param \Illuminate\Http\JsonResponse $tokenResponse The response containing the token.
     * @param array                         $response      Additional response data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function respondWithToken($tokenResponse, array $response = [])
    {
        $data = json_decode($tokenResponse->getContent());

        $response['access_token'] = $data->access_token;
        $response['refresh_token'] = $data->refresh_token;
        $response['token_type'] = 'bearer';
        $response['expires_in'] = $data->expires_in;

        return response()->json($response);
    }
}
