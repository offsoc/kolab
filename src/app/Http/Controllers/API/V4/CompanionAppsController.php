<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanionAppsController extends Controller
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
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $notificationToken = $request->notificationToken;
        $deviceId = $request->deviceId;

        \Log::info("Registering app. Notification token: {$notificationToken} Device id: {$deviceId}");

        $app = \App\CompanionApp::where('device_id', $deviceId)->first();
        if (!$app) {
            $app = new \App\CompanionApp();
            $app->user_id = $user->id;
            $app->device_id = $deviceId;
            $app->mfa_enabled = true;
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
}
