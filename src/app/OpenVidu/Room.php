<?php

namespace App\OpenVidu;

use App\Traits\OpenVidu\RoomSettingsTrait;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use RoomSettingsTrait;

    protected $fillable = [
        'user_id',
        'name'
    ];

    protected $table = 'openvidu_rooms';

    private static $client = null;

    private function client()
    {
        if (!self::$client) {
            self::$client = new \GuzzleHttp\Client(
                [
                    'http_errors' => false, // No exceptions from Guzzle
                    'base_uri' => \config('openvidu.api_url'),
                    'verify' => \config('openvidu.api_verify_tls'),
                    'auth' => [
                        \config('openvidu.api_username'),
                        \config('openvidu.api_password')
                    ]
                ]
            );
        }

        return self::$client;
    }

    public function createSession()
    {
        $response = $this->client()->request(
            'POST',
            "sessions",
            [
                'json' => [
                    'mediaMode' => 'ROUTED',
                    'recordingMode' => 'MANUAL'
                ]
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->session_id = null;
            $this->save();
        }

        $session = json_decode($response->getBody(), true);

        $this->session_id = $session['id'];
        $this->save();

        return $session;
    }

    public function getSessionToken($role = 'PUBLISHER')
    {
        $response = $this->client()->request(
            'POST',
            'tokens',
            [
                'json' => [
                    'session' => $this->session_id,
                    'role' => $role
                ]
            ]
        );

        $json = json_decode($response->getBody(), true);

        return $json;
    }

    public function hasSession()
    {
        if (!$this->session_id) {
            return false;
        }

        $response = $this->client()->request('GET', "sessions/{$this->session_id}");

        return $response->getStatusCode() == 200;
    }
}
