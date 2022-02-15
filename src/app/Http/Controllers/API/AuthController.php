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
     * @param \App\User   $user         User model object
     * @param string      $password     Plain text password
     * @param string|null $secondFactor Second factor code if available
     */
    public static function logonResponse(User $user, string $password, string $secondFactor = null)
    {
        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'username' => $user->email,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => \config('auth.proxy.client_id'),
            'client_secret' => \config('auth.proxy.client_secret'),
            'scopes' => '[*]',
            'secondfactor' => $secondFactor
        ]);

        $tokenResponse = app()->handle($proxyRequest);

        $response = V4\UsersController::userResponse($user);
        $response['status'] = 'success';

        return self::respondWithToken($tokenResponse, $response);
    }

    /**
     * Get an oauth token via given credentials.
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
                'email' => 'required|min:3',
                'password' => 'required|min:1',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $user = \App\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => \trans('auth.failed')], 401);
        }

        return self::logonResponse($user, $request->password, $request->secondfactor);
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
                'message' => \trans('auth.logoutsuccess')
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

    /**
     * Refresh the token and respond with it.
     *
     * @param \Illuminate\Http\Request $request  The API request.
     * @param array                    $response Additional response data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function refreshAndRespond(Request $request, array $response = [])
    {
        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => \config('auth.proxy.client_id'),
            'client_secret' => \config('auth.proxy.client_secret'),
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

        if ($tokenResponse->getStatusCode() != 200) {
            if (isset($data->error) && $data->error == 'secondfactor' && isset($data->error_description)) {
                $errors = ['secondfactor' => $data->error_description];
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return response()->json(['status' => 'error', 'message' => \trans('auth.failed')], 401);
        }

        $response['access_token'] = $data->access_token;
        $response['refresh_token'] = $data->refresh_token;
        $response['token_type'] = 'bearer';
        $response['expires_in'] = $data->expires_in;

        return response()->json($response);
    }
}
