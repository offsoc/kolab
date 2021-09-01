<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\OpenVidu\Connection;
use App\OpenVidu\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OpenViduController extends Controller
{
    public const AUTH_HEADER = 'X-Meet-Auth-Token';

    /**
     * Accept the room join request.
     *
     * @param string $id    Room identifier (name)
     * @param string $reqid Request identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptJoinRequest($id, $reqid)
    {
        $room = Room::where('name', $id)->first();

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        // Only the moderator can do it
        if (!$this->isModerator($room)) {
            return $this->errorResponse(403);
        }

        if (!$room->requestAccept($reqid)) {
            return $this->errorResponse(500, \trans('meet.session-request-accept-error'));
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Deny the room join request.
     *
     * @param string $id    Room identifier (name)
     * @param string $reqid Request identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function denyJoinRequest($id, $reqid)
    {
        $room = Room::where('name', $id)->first();

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        // Only the moderator can do it
        if (!$this->isModerator($room)) {
            return $this->errorResponse(403);
        }

        if (!$room->requestDeny($reqid)) {
            return $this->errorResponse(500, \trans('meet.session-request-deny-error'));
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Create a connection for screen sharing.
     *
     * @param string $id Room identifier (name)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createConnection($id)
    {
        $room = Room::where('name', $id)->first();

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        $connection = $this->getConnectionFromRequest();

        if (
            !$connection
            || $connection->session_id != $room->session_id
            || ($connection->role & Room::ROLE_PUBLISHER) == 0
        ) {
            return $this->errorResponse(403);
        }

        $response = $room->getSessionToken(Room::ROLE_SCREEN);

        return response()->json(['status' => 'success', 'token' => $response['token']]);
    }

    /**
     * Listing of rooms that belong to the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::guard()->user();

        $rooms = Room::where('user_id', $user->id)->orderBy('name')->get();

        if (count($rooms) == 0) {
            // Create a room for the user (with a random and unique name)
            while (true) {
                $name = strtolower(\App\Utils::randStr(3, 3, '-'));
                if (!Room::where('name', $name)->count()) {
                    break;
                }
            }

            $room = Room::create([
                    'name' => $name,
                    'user_id' => $user->id
            ]);

            $rooms = collect([$room]);
        }

        $result = [
            'list' => $rooms,
            'count' => count($rooms),
        ];

        return response()->json($result);
    }

    /**
     * Join the room session. Each room has one owner, and the room isn't open until the owner
     * joins (and effectively creates the session).
     *
     * @param string $id Room identifier (name)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinRoom($id)
    {
        $room = Room::where('name', $id)->first();

        // Room does not exist, or the owner is deleted
        if (!$room || !$room->owner) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        // Check if there's still a valid meet entitlement for the room owner
        if (!$room->owner->hasSku('meet')) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        $user = Auth::guard()->user();
        $isOwner = $user && $user->id == $room->user_id;
        $init = !empty(request()->input('init'));

        // There's no existing session
        if (!$room->hasSession()) {
            // Participants can't join the room until the session is created by the owner
            if (!$isOwner) {
                return $this->errorResponse(422, \trans('meet.session-not-found'), ['code' => 323]);
            }

            // The room owner can create the session on request
            if (!$init) {
                return $this->errorResponse(422, \trans('meet.session-not-found'), ['code' => 324]);
            }

            $session = $room->createSession();

            if (empty($session)) {
                return $this->errorResponse(500, \trans('meet.session-create-error'));
            }
        }

        $settings = $room->getSettings(['locked', 'nomedia', 'password']);
        $password = (string) $settings['password'];

        $config = [
            'locked' => $settings['locked'] === 'true',
            'nomedia' => $settings['nomedia'] === 'true',
            'password' => $isOwner ? $password : '',
            'requires_password' => !$isOwner && strlen($password),
        ];

        $response = ['config' => $config];

        // Validate room password
        if (!$isOwner && strlen($password)) {
            $request_password = request()->input('password');
            if ($request_password !== $password) {
                return $this->errorResponse(422, \trans('meet.session-password-error'), $response + ['code' => 325]);
            }
        }

        // Handle locked room
        if (!$isOwner && $config['locked']) {
            $nickname = request()->input('nickname');
            $picture = request()->input('picture');
            $requestId = request()->input('requestId');

            $request = $requestId ? $room->requestGet($requestId) : null;

            $error = \trans('meet.session-room-locked-error');

            // Request already has been processed (not accepted yet, but it could be denied)
            if (empty($request['status']) || $request['status'] != Room::REQUEST_ACCEPTED) {
                if (!$request) {
                    if (empty($nickname) || empty($requestId) || !preg_match('/^[a-z0-9]{8,32}$/i', $requestId)) {
                        return $this->errorResponse(422, $error, $response + ['code' => 326]);
                    }

                    if (empty($picture)) {
                        $svg = file_get_contents(resource_path('images/user.svg'));
                        $picture = 'data:image/svg+xml;base64,' . base64_encode($svg);
                    } elseif (!preg_match('|^data:image/png;base64,[a-zA-Z0-9=+/]+$|', $picture)) {
                        return $this->errorResponse(422, $error, $response + ['code' => 326]);
                    }

                    // TODO: Resize when big/make safe the user picture?

                    $request = ['nickname' => $nickname, 'requestId' => $requestId, 'picture' => $picture];

                    if (!$room->requestSave($requestId, $request)) {
                        // FIXME: should we use error code 500?
                        return $this->errorResponse(422, $error, $response + ['code' => 326]);
                    }

                    // Send the request (signal) to the owner
                    $result = $room->signal('joinRequest', $request, Room::ROLE_MODERATOR);
                }

                return $this->errorResponse(422, $error, $response + ['code' => 327]);
            }
        }

        // Initialize connection tokens
        if ($init) {
            // Choose the connection role
            $canPublish = !empty(request()->input('canPublish')) && (empty($config['nomedia']) || $isOwner);
            $role = $canPublish ? Room::ROLE_PUBLISHER : Room::ROLE_SUBSCRIBER;
            if ($isOwner) {
                $role |= Room::ROLE_MODERATOR;
                $role |= Room::ROLE_OWNER;
            }

            // Create session token for the current user/connection
            $response = $room->getSessionToken($role);

            if (empty($response)) {
                return $this->errorResponse(500, \trans('meet.session-join-error'));
            }

            // Get up-to-date connections metadata
            $response['connections'] = $room->getSessionConnections();

            $response_code = 200;
            $response['role'] = $role;
            $response['config'] = $config;
        } else {
            $response_code = 422;
            $response['code'] = 322;
        }

        return response()->json($response, $response_code);
    }

    /**
     * Set the domain configuration.
     *
     * @param string $id Room identifier (name)
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function setRoomConfig($id)
    {
        $room = Room::where('name', $id)->first();

        // Room does not exist, or the owner is deleted
        if (!$room || !$room->owner) {
            return $this->errorResponse(404);
        }

        $user = Auth::guard()->user();

        // Only room owner can configure the room
        if ($user->id != $room->user_id) {
            return $this->errorResponse(403);
        }

        $input = request()->input();
        $errors = [];

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'password':
                    if ($value === null || $value === '') {
                        $input[$key] = null;
                    } else {
                        // TODO: Do we have to validate the password in any way?
                    }
                    break;

                case 'locked':
                    $input[$key] = $value ? 'true' : null;
                    break;

                case 'nomedia':
                    $input[$key] = $value ? 'true' : null;
                    break;

                default:
                    $errors[$key] = \trans('meet.room-unsupported-option-error');
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        if (!empty($input)) {
            $room->setSettings($input);
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans('meet.room-setconfig-success'),
        ]);
    }

    /**
     * Update the participant/connection parameters (e.g. role).
     *
     * @param string $id   Room identifier (name)
     * @param string $conn Connection identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateConnection($id, $conn)
    {
        $connection = Connection::where('id', $conn)->first();

        // There's no such connection, bye bye
        if (!$connection || $connection->room->name != $id) {
            return $this->errorResponse(404, \trans('meet.connection-not-found'));
        }

        foreach (request()->input() as $key => $value) {
            switch ($key) {
                case 'hand':
                    // Only possible on user's own connection(s)
                    if (!$this->isSelfConnection($connection)) {
                        return $this->errorResponse(403);
                    }

                    if ($value) {
                        // Store current time, so we know the order in the queue
                        $connection->metadata = ['hand' => time()] + $connection->metadata;
                    } else {
                        $connection->metadata = array_diff_key($connection->metadata, ['hand' => 0]);
                    }

                    break;

                case 'language':
                    // Only the moderator can do it
                    if (!$this->isModerator($connection->room)) {
                        return $this->errorResponse(403);
                    }

                    if ($value) {
                        if (preg_match('/^[a-z]{2}$/', $value)) {
                            $connection->metadata = ['language' => $value] + $connection->metadata;
                        }
                    } else {
                        $connection->metadata = array_diff_key($connection->metadata, ['language' => 0]);
                    }

                    break;

                case 'role':
                    // Only the moderator can do it
                    if (!$this->isModerator($connection->room)) {
                        return $this->errorResponse(403);
                    }

                    // The 'owner' role is not assignable
                    if ($value & Room::ROLE_OWNER && !($connection->role & Room::ROLE_OWNER)) {
                        return $this->errorResponse(403);
                    } elseif (!($value & Room::ROLE_OWNER) && ($connection->role & Room::ROLE_OWNER)) {
                        return $this->errorResponse(403);
                    }

                    // The room owner has always a 'moderator' role
                    if (!($value & Room::ROLE_MODERATOR) && $connection->role & Room::ROLE_OWNER) {
                        $value |= Room::ROLE_MODERATOR;
                    }

                    // Promotion to publisher? Put the user hand down
                    if ($value & Room::ROLE_PUBLISHER && !($connection->role & Room::ROLE_PUBLISHER)) {
                        $connection->metadata = array_diff_key($connection->metadata, ['hand' => 0]);
                    }

                    // Non-publisher cannot be a language interpreter
                    if (!($value & Room::ROLE_PUBLISHER)) {
                        $connection->metadata = array_diff_key($connection->metadata, ['language' => 0]);
                    }

                    $connection->{$key} = $value;
                    break;
            }
        }

        // The connection observer will send a signal to everyone when needed
        $connection->save();

        return response()->json(['status' => 'success']);
    }

    /**
     * Webhook as triggered from OpenVidu server
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function webhook(Request $request)
    {
        \Log::debug($request->getContent());

        switch ((string) $request->input('event')) {
            case 'sessionDestroyed':
                // When all participants left the room OpenVidu dispatches sessionDestroyed
                // event. We'll remove the session reference from the database.
                $sessionId = $request->input('sessionId');
                $room = Room::where('session_id', $sessionId)->first();

                if ($room) {
                    $room->session_id = null;
                    $room->save();
                }

                // Remove all connections
                // Note: We could remove connections one-by-one via the 'participantLeft' event
                // but that could create many INSERTs when the session (with many participants) ends
                // So, it is better to remove them all in a single INSERT.
                Connection::where('session_id', $sessionId)->delete();

                break;
        }

        return response('Success', 200);
    }

    /**
     * Check if current user is a moderator for the specified room.
     *
     * @param \App\OpenVidu\Room $room The room
     *
     * @return bool True if the current user is the room moderator
     */
    protected function isModerator(Room $room): bool
    {
        $user = Auth::guard()->user();

        // The room owner is a moderator
        if ($user && $user->id == $room->user_id) {
            return true;
        }

        // Moderator's authentication via the extra request header
        if (
            ($connection = $this->getConnectionFromRequest())
            && $connection->session_id === $room->session_id
            && $connection->role & Room::ROLE_MODERATOR
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if current user "owns" the specified connection.
     *
     * @param \App\OpenVidu\Connection $connection The connection
     *
     * @return bool
     */
    protected function isSelfConnection(Connection $connection): bool
    {
        return ($conn = $this->getConnectionFromRequest())
            && $conn->id === $connection->id;
    }

    /**
     * Get the connection object for the token in current request headers.
     * It will also validate the token.
     *
     * @return \App\OpenVidu\Connection|null Connection (if exists and the token is valid)
     */
    protected function getConnectionFromRequest()
    {
        // Authenticate the user via the extra request header
        if ($token = request()->header(self::AUTH_HEADER)) {
            list($connId, ) = explode(':', base64_decode($token), 2);

            if (
                ($connection = Connection::find($connId))
                && $connection->metadata['authToken'] === $token
            ) {
                return $connection;
            }
        }

        return null;
    }
}
