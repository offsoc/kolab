<?php

namespace App\OpenVidu;

use App\Traits\SettingsTrait;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use SettingsTrait;

    protected $fillable = [
        'user_id',
        'name'
    ];

    protected $table = 'openvidu_rooms';

    /** @var \GuzzleHttp\Client|null HTTP client instance */
    private static $client = null;


    /**
     * Creates HTTP client for connections to OpenVidu server
     *
     * @return \GuzzleHttp\Client HTTP client instance
     */
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

    /**
     * Create a OpenVidu session
     *
     * @return array|null Session data on success, NULL otherwise
     */
    public function createSession(): ?array
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

    /**
     * Delete a OpenVidu session
     *
     * @return bool
     */
    public function deleteSession(): bool
    {
        if (!$this->session_id) {
            return true;
        }

        $response = $this->client()->request(
            'DELETE',
            "sessions/" . $this->session_id,
        );

        if ($response->getStatusCode() == 204) {
            $this->session_id = null;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Create a OpenVidu session (connection) token
     *
     * @return array|null Token data on success, NULL otherwise
     */
    public function getSessionToken($role = 'PUBLISHER'): ?array
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

        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody(), true);

            if ($rewrite_host = \config('openvidu.rewrite_host')) {
                $json['token'] = preg_replace(
                    '|^(wss?://)([^?/]+)|',
                    '\\1' . $rewrite_host,
                    $json['token']
                );
            }

            return $json;
        }

        return null;
    }

    /**
     * Check if the room has an active session
     *
     * @return bool True when the session exists, False otherwise
     */
    public function hasSession(): bool
    {
        if (!$this->session_id) {
            return false;
        }

        $response = $this->client()->request('GET', "sessions/{$this->session_id}");

        return $response->getStatusCode() == 200;
    }

    /**
     * The room owner.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }

    /**
     * Any (additional) properties of this room.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function settings()
    {
        return $this->hasMany('App\OpenVidu\RoomSetting', 'room_id');
    }
}
