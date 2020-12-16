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

    public const ROLE_MODERATOR = 'MODERATOR';
    public const ROLE_PUBLISHER = 'PUBLISHER';
    public const ROLE_SUBSCRIBER = 'SUBSCRIBER';

    public const REQUEST_ACCEPTED = 'accepted';
    public const REQUEST_DENIED = 'denied';

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
     * Destroy a OpenVidu connection
     *
     * @param string $conn Connection identifier
     *
     * @return bool True on success, False otherwise
     * @throws \Exception if session does not exist
     */
    public function closeOVConnection($conn): bool
    {
        if (!$this->session_id) {
            throw new \Exception("The room session does not exist");
        }

        $url = 'sessions/' . $this->session_id . '/connection/' . urlencode($conn);

        $response = $this->client()->request('DELETE', $url);

        return $response->getStatusCode() == 204;
    }

    /**
     * Fetch a OpenVidu connection information.
     *
     * @param string $conn Connection identifier
     *
     * @return ?array Connection data on success, Null otherwise
     * @throws \Exception if session does not exist
     */
    public function getOVConnection($conn): ?array
    {
        // Note: getOVConnection() not getConnection() because Eloquent\Model::getConnection() exists
        // TODO: Maybe use some other name? getParticipant?
        if (!$this->session_id) {
            throw new \Exception("The room session does not exist");
        }

        $url = 'sessions/' . $this->session_id . '/connection/' . urlencode($conn);

        $response = $this->client()->request('GET', $url);

        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody(), true);
        }

        return null;
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
            return null;
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
    public function getSessionToken($role = self::ROLE_PUBLISHER): ?array
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
     * @param string       $name   Signal name (type)
     * @param array        $data   Signal data array
     * @param array|string $target List of target connections, Null for all connections.
     *                             It can be also a participant role.
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
            'session' => $this->session_id,
            'type' => $name,
            'data' => $data ? json_encode($data) : '',
        ];

        // Get connection IDs by participant role
        if (is_string($target)) {
            // TODO: We should probably store this in our database/redis. I foresee a use-case
            //       for such a connections store on our side, e.g. keeping participant
            //       metadata, e.g. selected language, extra roles like a "language interpreter", etc.

            $response = $this->client()->request('GET', 'sessions/' . $this->session_id);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $json = json_decode($response->getBody(), true);
            $connections = [];

            foreach ($json['connections']['content'] as $connection) {
                if ($connection['role'] === $target) {
                    $connections[] = $connection['id'];
                    break;
                }
            }

            if (empty($connections)) {
                return false;
            }

            $target = $connections;
        }

        if (!empty($target)) {
            $post['to'] = $target;
        }

        $response = $this->client()->request('POST', 'signal', ['json' => $post]);

        return $response->getStatusCode() == 200;
    }
}
