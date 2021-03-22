<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $user = Auth::guard()->user();
        if (!$user) {
            throw new  \Exception("Authentication required.");
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
        }

        $app->notification_token = $notificationToken;
        $app->save();

        $result['status'] = 'success';
        return response()->json($result);
    }
}
