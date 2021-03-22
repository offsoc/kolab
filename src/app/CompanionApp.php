<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a CompanionApp.
 *
 * A CompanionApp is an kolab companion app that the user registered
 */
class CompanionApp extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'device_id',
        'notification_token',
        'mfa_enabled',
    ];

    /**
    * Send a notification via firebase.
    *
    * @return bool true if a notification has been sent
    */
    private static function pushFirebaseNotification($deviceIds, $data)
    {
        \Log::debug("sending notification to " . var_export($deviceIds, true));
        $url = \config('firebase.api_url');
        $apiKey = \config('firebase.api_key');

        $fields = [
            'registration_ids' => $deviceIds,
            'data' => $data
        ];

        $headers = array(
            'Content-Type:application/json',
            "Authorization:key={$apiKey}"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if ($result === false) {
            throw new \Exception('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    /**
    * Send a notification to a user.
    *
    * @return bool true if a notification has been sent
    */
    public static function notifyUser($userId, $data)
    {
        $notificationTokens = \App\CompanionApp::where('user_id', $userId)
            ->where('mfa_enabled', true)
            ->get()
            ->map(function ($app) {
                return $app->notification_token;
            })
            ->all();

        if (empty($notificationTokens)) {
            \Log::debug("There is no 2fa device to notify.");
            return false;
        }

        self::pushFirebaseNotification($notificationTokens, $data);
        return true;
    }
}
