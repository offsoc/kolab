<?php

namespace App\OpenVidu;

use App\Traits\SettingsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The eloquent definition of a Room.
 *
 * @property int     $id         Room identifier
 * @property string  $name       Room name
 * @property int     $user_id    Room owner
 * @property ?string $session_id OpenVidu session identifier
 */
class Room extends Model
{
    use SettingsTrait;

    public const ROLE_SUBSCRIBER = 1 << 0;
    public const ROLE_PUBLISHER = 1 << 1;
    public const ROLE_MODERATOR = 1 << 2;
    public const ROLE_SCREEN = 1 << 3;
    public const ROLE_OWNER = 1 << 4;

    public const REQUEST_ACCEPTED = 'accepted';
    public const REQUEST_DENIED = 'denied';

    private const OV_ROLE_MODERATOR = 'MODERATOR';
    private const OV_ROLE_PUBLISHER = 'PUBLISHER';
    private const OV_ROLE_SUBSCRIBER = 'SUBSCRIBER';

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
                    'base_uri' => \config('meet.api_url'),
                    'verify' => \config('meet.api_verify_tls'),
                    'headers' => [
                        'X-Auth-Token' => \config('meet.api_token'),
                    ],
                    'connect_timeout' => 10,
                    'timeout' => 10,
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                        $threshold = \config('logging.slow_log');
                        if ($threshold && ($sec = $stats->getTransferTime()) > $threshold) {
                            $url = $stats->getEffectiveUri();
                            $method = $stats->getRequest()->getMethod();
                            \Log::warning(sprintf("[STATS] %s %s: %.4f sec.", $method, $url, $sec));
                        }
                    },
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
        $params = [
            'json' => [ /* request params here */ ]
        ];

        $response = $this->client()->request('POST', "sessions", $params);

        if ($response->getStatusCode() !== 200) {
            $this->logError("Failed to create the meet session", $response);
            $this->session_id = null;
            $this->save();
            return null;
        }

        $session = json_decode($response->getBody(), true);

        $this->session_id = $session['id'];
        $this->save();

        return $session;
    }

    /**
     * Create a OpenVidu session (connection) token
     *
     * @param int $role User role (see self::ROLE_* constants)
     *
     * @return array|null Token data on success, NULL otherwise
     * @throws \Exception if session does not exist
     */
    public function getSessionToken($role = self::ROLE_SUBSCRIBER): ?array
    {
        if (!$this->session_id) {
            throw new \Exception("The room session does not exist");
        }

        $url = 'sessions/' . $this->session_id . '/connection';
        $post = [
            'json' => [
                'role' => $role,
            ]
        ];

        $response = $this->client()->request('POST', $url, $post);

        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody(), true);

            return [
                'token' => $json['token'],
                'role' => $role,
            ];
        }

        $this->logError("Failed to create the meet peer connection", $response);

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

        $this->logError("Failed to check that a meet session exists", $response);

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
     * Accept the join request.
     *
     * @param string $id Request identifier
     *
     * @return bool True on success, False on failure
     */
    public function requestAccept(string $id): bool
    {
        $request = Cache::get($this->session_id . '-' . $id);

        if ($request) {
            $request['status'] = self::REQUEST_ACCEPTED;

            return Cache::put($this->session_id . '-' . $id, $request, now()->addHours(1));
        }

        return false;
    }

    /**
     * Deny the join request.
     *
     * @param string $id Request identifier
     *
     * @return bool True on success, False on failure
     */
    public function requestDeny(string $id): bool
    {
        $request = Cache::get($this->session_id . '-' . $id);

        if ($request) {
            $request['status'] = self::REQUEST_DENIED;

            return Cache::put($this->session_id . '-' . $id, $request, now()->addHours(1));
        }

        return false;
    }

    /**
     * Get the join request data.
     *
     * @param string $id Request identifier
     *
     * @return array|null Request data (e.g. nickname, status, picture?)
     */
    public function requestGet(string $id): ?array
    {
        return Cache::get($this->session_id . '-' . $id);
    }

    /**
     * Save the join request.
     *
     * @param string $id      Request identifier
     * @param array  $request Request data
     *
     * @return bool True on success, False on failure
     */
    public function requestSave(string $id, array $request): bool
    {
        // We don't really need the picture in the cache
        // As we use this cache for the request status only
        unset($request['picture']);

        return Cache::put($this->session_id . '-' . $id, $request, now()->addHours(1));
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

    /**
     * Send a OpenVidu signal to the session participants (connections)
     *
     * @param string $name   Signal name (type)
     * @param array  $data   Signal data array
     * @param int    $target Limit targets by their participant role
     *
     * @return bool True on success, False on failure
     * @throws \Exception if session does not exist
     */
    public function signal(string $name, array $data = [], $target = null): bool
    {
        if (!$this->session_id) {
            throw new \Exception("The room session does not exist");
        }

        $post = [
            'roomId' => $this->session_id,
            'type'   => $name,
            'role'   => $target,
            'data'   => $data,
        ];

        $response = $this->client()->request('POST', 'signal', ['json' => $post]);

        $this->logError("Failed to send a signal to the meet session", $response);

        return $response->getStatusCode() == 200;
    }

    /**
     * Log an error for a failed request to the meet server
     *
     * @param string $str      The error string
     * @param object $response Guzzle client response
     */
    private function logError(string $str, $response)
    {
        $code = $response->getStatusCode();
        if ($code != 200) {
            \Log::error("$str [$code]");
        }
    }
}
