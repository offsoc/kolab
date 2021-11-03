<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\ResourceController;
use App\Utils;
use App\Tenant;
use Laravel\Passport\Token;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use BaconQrCode;

class CompanionAppsController extends ResourceController
{
    /**
    * Register a companion app.
    *
    * @param \Illuminate\Http\Request $request The API request.
    *
    * @return \Illuminate\Http\JsonResponse The response
    */
    public function register(Request $request)
    {
        $user = $this->guard()->user();

        $v = Validator::make(
            $request->all(),
            [
                'notificationToken' => 'required|min:4|max:512',
                'deviceId' => 'required|min:4|max:64',
                'name' => 'required|max:512',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $notificationToken = $request->notificationToken;
        $deviceId = $request->deviceId;
        $name = $request->name;

        \Log::info("Registering app. Notification token: {$notificationToken} Device id: {$deviceId} Name: {$name}");

        $app = \App\CompanionApp::where('device_id', $deviceId)->first();
        if (!$app) {
            $app = new \App\CompanionApp();
            $app->user_id = $user->id;
            $app->device_id = $deviceId;
            $app->mfa_enabled = true;
            $app->name = $name;
        } else {
            //FIXME this allows a user to probe for another users deviceId
            if ($app->user_id != $user->id) {
                \Log::warning("User mismatch on device registration. Expected {$user->id} but found {$app->user_id}");
                return $this->errorResponse(403);
            }
        }

        $app->notification_token = $notificationToken;
        $app->save();

        return response()->json(['status' => 'success']);
    }


    /**
     * Generate a QR-code image for a string
     *
     * @param string $data data to encode
     *
     * @return string
     */
    private static function generateQRCode($data)
    {
        $renderer_style = new BaconQrCode\Renderer\RendererStyle\RendererStyle(300, 1);
        $renderer_image = new BaconQrCode\Renderer\Image\SvgImageBackEnd();
        $renderer = new BaconQrCode\Renderer\ImageRenderer($renderer_style, $renderer_image);
        $writer   = new BaconQrCode\Writer($renderer);

        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($data));
    }

    /**
     * Revoke all companion app devices.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function revokeAll()
    {
        $user = $this->guard()->user();
        \App\CompanionApp::where('user_id', $user->id)->delete();

        // Revoke all companion app tokens
        $clientIdentifier = \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_id');
        $tokens = Token::where('user_id', $user->id)->where('client_id', $clientIdentifier)->get();

        $tokenRepository = app(TokenRepository::class);
        $refreshTokenRepository = app(RefreshTokenRepository::class);

        foreach ($tokens as $token) {
            $tokenRepository->revokeAccessToken($token->id);
            $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($token->id);
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans("app.companion-deleteall-success"),
        ]);
    }

    /**
     * List devices.
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

        $result = \App\CompanionApp::where('user_id', $user->id);

        $result = $result->orderBy('created_at')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        // Process the result
        $result = $result->map(
            function ($device) {
                return $device->toArray();
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
     * Get the information about the specified companion app.
     *
     * @param string $id CompanionApp identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = \App\CompanionApp::find($id);
        if (!$result) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $result->user_id) {
            return $this->errorResponse(403);
        }

        return response()->json($result->toArray());
    }

    /**
     * Retrieve the pairing information encoded into a qrcode image.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pairing()
    {
        $user = $this->guard()->user();

        $clientIdentifier = \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_id');
        $clientSecret = \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_secret');
        if (empty($clientIdentifier) || empty($clientSecret)) {
            \Log::warning("Empty client identifier or secret. Can't generate qr-code.");
            return $this->errorResponse(500);
        }

        $response['qrcode'] = self::generateQRCode(
            json_encode([
                "serverUrl" => Utils::serviceUrl('', $user->tenant_id),
                "clientIdentifier" => \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_id'),
                "clientSecret" => \App\Tenant::getConfig($user->tenant_id, 'auth.companion_app.client_secret'),
                "username" => $user->email
            ])
        );

        return response()->json($response);
    }
}
