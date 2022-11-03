<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\ResourceController;
use App\Utils;
use App\Tenant;
use Laravel\Passport\Passport;
use Laravel\Passport\ClientRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use BaconQrCode;

class CompanionAppsController extends ResourceController
{
    /**
     * Remove the specified companion app.
     *
     * @param string $id Companion app identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $companion = \App\CompanionApp::find($id);
        if (!$companion) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $companion->user_id) {
            return $this->errorResponse(403);
        }

        // Revoke client and tokens
        $client = $companion->passportClient();
        if ($client) {
            $clientRepository = app(ClientRepository::class);
            $clientRepository->delete($client);
        }

        $companion->delete();

        return response()->json([
            'status' => 'success',
            'message' => \trans('app.companion-delete-success'),
        ]);
    }

    /**
     * Create a companion app.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $this->guard()->user();

        $v = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:512',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $app = \App\CompanionApp::create([
            'name' => $request->name,
            'user_id' =>  $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => \trans('app.companion-create-success'),
            'id' => $app->id
        ]);
    }

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
                'notificationToken' => 'required|string|min:4|max:512',
                'deviceId' => 'required|string|min:4|max:64',
                'companionId' => 'required|max:64',
                'name' => 'required|string|max:512',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $notificationToken = $request->notificationToken;
        $deviceId = $request->deviceId;
        $companionId = $request->companionId;
        $name = $request->name;

        \Log::info("Registering app. Notification token: {$notificationToken} Device id: {$deviceId} Name: {$name}");

        $app = \App\CompanionApp::find($companionId);
        if (!$app) {
            return $this->errorResponse(404);
        }

        if ($app->user_id != $user->id) {
            \Log::warning("User mismatch on device registration. Expected {$user->id} but found {$app->user_id}");
            return $this->errorResponse(403);
        }

        $app->device_id = $deviceId;
        $app->mfa_enabled = true;
        $app->name = $name;
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
                return array_merge($device->toArray(), [
                    'isReady' => $device->isPaired()
                ]);
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

        return response()->json(array_merge($result->toArray(), [
            'statusInfo' => [
                'isReady' => $result->isPaired()
            ]
        ]));
    }

    /**
     * Retrieve the pairing information encoded into a qrcode image.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pairing($id)
    {
        $result = \App\CompanionApp::find($id);
        if (!$result) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $result->user_id) {
            return $this->errorResponse(403);
        }

        $client = $result->passportClient();
        if (!$client) {
            $client = Passport::client()->forceFill([
                'user_id' => $user->id,
                'name' => "CompanionApp Password Grant Client",
                'secret' => Str::random(40),
                'provider' => 'users',
                'redirect' => 'https://' . \config('app.website_domain'),
                'personal_access_client' => 0,
                'password_client' => 1,
                'revoked' => false,
                'allowed_scopes' => "mfa"
            ]);
            $client->save();

            $result->setPassportClient($client);
            $result->save();
        }
        $response['qrcode'] = self::generateQRCode(
            json_encode([
                "serverUrl" => Utils::serviceUrl('', $user->tenant_id),
                "clientIdentifier" => $client->id,
                "clientSecret" => $client->secret,
                "companionId" => $id,
                "username" => $user->email
            ])
        );

        return response()->json($response);
    }
}
