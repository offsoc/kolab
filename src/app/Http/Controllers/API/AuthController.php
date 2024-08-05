<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Response as Psr7Response;

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

        if (!empty(request()->input('refresh'))) {
            return $this->refreshAndRespond(request(), $user);
        }

        $response = V4\UsersController::userResponse($user);

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
            'scope' => 'api',
            'secondfactor' => $secondFactor
        ]);
        $proxyRequest->headers->set('X-Client-IP', request()->ip());

        $tokenResponse = app()->handle($proxyRequest);

        return self::respondWithToken($tokenResponse, $user);
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
            return response()->json(['status' => 'error', 'message' => self::trans('auth.failed')], 401);
        }

        return self::logonResponse($user, $request->password, $request->secondfactor);
    }

    /**
     * Approval request for the oauth authorization endpoint
     *
     * * The user is authenticated via the regular login page
     * * We assume implicit consent in the Authorization page
     * * Ultimately we return an authorization code to the caller via the redirect_uri
     *
     * The implementation is based on Laravel\Passport\Http\Controllers\AuthorizationController
     *
     * @param ServerRequestInterface   $psrRequest PSR request
     * @param \Illuminate\Http\Request $request    The API request
     * @param AuthorizationServer      $server     Authorization server
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function oauthApprove(ServerRequestInterface $psrRequest, Request $request, AuthorizationServer $server)
    {
        if ($request->response_type != 'code') {
            return self::errorResponse(422, self::trans('validation.invalidvalueof', ['attribute' => 'response_type']));
        }

        try {
            // league/oauth2-server/src/Grant/ code expects GET parameters, but we're using POST here
            $psrRequest = $psrRequest->withQueryParams($request->input());

            $authRequest = $server->validateAuthorizationRequest($psrRequest);

            $user = Auth::guard()->user();

            // TODO I'm not sure if we should still execute this to deny the request
            $authRequest->setUser(new \Laravel\Passport\Bridge\User($user->getAuthIdentifier()));
            $authRequest->setAuthorizationApproved(true);

            // This will generate a 302 redirect to the redirect_uri with the generated authorization code
            $response = $server->completeAuthorizationRequest($authRequest, new Psr7Response());
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            // Note: We don't want 401 or 400 codes here, use 422 which is used in our API
            $code = $e->getHttpStatusCode();
            return self::errorResponse($code < 500 ? 422 : 500, $e->getMessage());
        } catch (\Exception $e) {
            return self::errorResponse(422, self::trans('auth.error.invalidrequest'));
        }

        return response()->json([
                'status' => 'success',
                'redirectUrl' => $response->getHeader('Location')[0],
        ]);
    }

    /**
     * Get the authenticated User information (using access token claims)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function oauthUserInfo()
    {
        $user = Auth::guard()->user();

        $response = [
            // Per OIDC spec. 'sub' must be always returned
            'sub' => $user->id,
        ];

        if ($user->tokenCan('email')) {
            $response['email'] = $user->email;
            $response['email_verified'] = $user->isActive();
            # At least synapse depends on a "settings" structure being available
            $response['settings'] = [ 'name' => $user->name() ];
        }

        // TODO: Other claims (https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims)
        // address: address
        // phone: phone_number and phone_number_verified
        // profile: name, family_name, given_name, middle_name, nickname, preferred_username,
        //    profile, picture, website, gender, birthdate, zoneinfo, locale, and updated_at

        return response()->json($response);
    }

    /**
     * Get the user (geo) location
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function location()
    {
        $ip = request()->ip();

        $response = [
            'ipAddress' => $ip,
            'countryCode' => \App\Utils::countryForIP($ip, ''),
        ];

        return response()->json($response);
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
                'message' => self::trans('auth.logoutsuccess')
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
     * @param ?\App\User               $user     The user being authenticated
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function refreshAndRespond(Request $request, $user = null)
    {
        $proxyRequest = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => \config('auth.proxy.client_id'),
            'client_secret' => \config('auth.proxy.client_secret'),
        ]);

        $tokenResponse = app()->handle($proxyRequest);

        return self::respondWithToken($tokenResponse, $user);
    }

    /**
     * Get the token array structure.
     *
     * @param \Symfony\Component\HttpFoundation\Response $tokenResponse The response containing the token.
     * @param ?\App\User                                 $user          The user being authenticated
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function respondWithToken($tokenResponse, $user = null)
    {
        $data = json_decode($tokenResponse->getContent());

        if ($tokenResponse->getStatusCode() != 200) {
            if (isset($data->error) && $data->error == 'secondfactor' && isset($data->error_description)) {
                $errors = ['secondfactor' => $data->error_description];
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }

            return response()->json(['status' => 'error', 'message' => self::trans('auth.failed')], 401);
        }

        if ($user) {
            $response = V4\UsersController::userResponse($user);
        } else {
            $response = [];
        }

        $response['status'] = 'success';
        $response['access_token'] = $data->access_token;
        $response['refresh_token'] = $data->refresh_token;
        $response['token_type'] = 'bearer';
        $response['expires_in'] = $data->expires_in;

        return response()->json($response);
    }
}
