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
    /** @var array<int, string> The attributes that are mass assignable */
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
    * @param array $deviceIds A list of device id's to send the notification to
    * @param array $data The data to include in the notification.
    *
    * @throws \Exception on notification failure
    * @return bool true if a notification has been sent
    */
    private static function pushFirebaseNotification($deviceIds, $data): bool
    {
        \Log::debug("sending notification to " . var_export($deviceIds, true));
        $apiKey = \config('firebase.api_key');

        $client = new \GuzzleHttp\Client(
            [
                'verify' => \config('firebase.api_verify_tls')
            ]
        );
        $response = $client->request(
            'POST',
            \config('firebase.api_url'),
            [
                'headers' => [
                        'Authorization' => "key={$apiKey}",
                ],
                'json' => [
                    'registration_ids' => $deviceIds,
                    'data' => $data
                ]
            ]
        );


        if ($response->getStatusCode() != 200) {
            throw new \Exception('FCM Send Error: ' . $response->getStatusCode());
        }
        return true;
    }

    /**
    * Send a notification to a user.
    *
    * @throws \Exception on notification failure
    * @return bool true if a notification has been sent
    */
    public static function notifyUser($userId, $data): bool
    {
        $notificationTokens = CompanionApp::where('user_id', $userId)
            ->where('mfa_enabled', true)
            ->pluck('notification_token')
            ->all();

        if (empty($notificationTokens)) {
            \Log::debug("There is no 2fa device to notify.");
            return false;
        }

        self::pushFirebaseNotification($notificationTokens, $data);
        return true;
    }
}
