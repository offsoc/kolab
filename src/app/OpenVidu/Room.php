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
                    'base_uri' => \config('openvidu.api_url'),
                    'verify' => \config('openvidu.api_verify_tls'),
                    'auth' => [
                        \config('openvidu.api_username'),
                        \config('openvidu.api_password')
                    ],
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
     * Returns metadata for every connection in a session.
     *
     * @return array Connections metadata, indexed by connection identifier
     * @throws \Exception if session does not exist
     */
    public function getSessionConnections(): array
    {
        if (!$this->session_id) {
            throw new \Exception("The room session does not exist");
        }

        return Connection::where('session_id', $this->session_id)
            // Ignore screen sharing connection for now
            ->whereRaw("(role & " . self::ROLE_SCREEN . ") = 0")
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                // Warning: Make sure to not return all metadata here as it might contain sensitive data.
                return [
                    'role' => $item->role,
                    'hand' => $item->metadata['hand'] ?? 0,
                    'language' => $item->metadata['language'] ?? null,
                ];
            })
            // Sort by order in the queue, so UI can re-build the existing queue in order
            ->sort(function ($a, $b) {
                return $a['hand'] <=> $b['hand'];
            })
            ->all();
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

        // FIXME: Looks like passing the role in 'data' param is the only way
        // to make it visible for everyone in a room. So, for example we can
        // handle/style subscribers/publishers/moderators differently on the
        // client-side. Is this a security issue?
        $data = ['role' => $role];

        $url = 'sessions/' . $this->session_id . '/connection';
        $post = [
            'json' => [
                'role' => self::OV_ROLE_PUBLISHER,
                'data' => json_encode($data)
            ]
        ];

        $response = $this->client()->request('POST', $url, $post);

        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody(), true);

            $authToken = base64_encode($json['id'] . ':' . \random_bytes(16));

            // Extract the 'token' part of the token, it will be used to authenticate the connection.
            // It will be needed in next iterations e.g. to authenticate moderators that aren't
            // Kolab4 users (or are just not logged in to Kolab4).
            // FIXME: we could as well generate our own token for auth purposes
            parse_str(parse_url($json['token'], PHP_URL_QUERY), $url);

            // Create the connection reference in our database
            $conn = new Connection();
            $conn->id = $json['id'];
            $conn->session_id = $this->session_id;
            $conn->room_id = $this->id;
            $conn->role = $role;
            $conn->metadata = ['token' => $url['token'], 'authToken' => $authToken];
            $conn->save();

            return [
                'session' => $this->session_id,
                'token' => $json['token'],
                'authToken' => $authToken,
                'connectionId' => $json['id'],
                'role' => $role,
            ];
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
     * @param string            $name   Signal name (type)
     * @param array             $data   Signal data array
     * @param null|int|string[] $target List of target connections, Null for all connections.
     *                                  It can be also a participant role.
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
        if (is_int($target)) {
            $connections = Connection::where('session_id', $this->session_id)
                ->whereRaw("(role & $target)")
                ->pluck('id')
                ->all();

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
