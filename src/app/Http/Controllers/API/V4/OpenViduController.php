<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\OpenVidu\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OpenViduController extends Controller
{
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

                    // Send the request (signal) to all moderators
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
     * Webhook as triggered from OpenVidu server
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function webhook(Request $request)
    {
        \Log::debug($request->getContent());

        // Authenticate the request
        if ($request->headers->get('X-Auth-Token') != \config('meet.webhook_token')) {
            return response('Unauthorized', 403);
        }

        $sessionId = (string) $request->input('roomId');
        $event = (string) $request->input('event');

        switch ($event) {
            case 'roomClosed':
                // When all participants left the room the server will dispatch roomClosed
                // event. We'll remove the session reference from the database.
                $room = Room::where('session_id', $sessionId)->first();

                if ($room) {
                    $room->session_id = null;
                    $room->save();
                }

                break;

            case 'joinRequestAccepted':
            case 'joinRequestDenied':
                $room = Room::where('session_id', $sessionId)->first();

                if ($room) {
                    $method = $event == 'joinRequestAccepted' ? 'requestAccept' : 'requestDeny';

                    $room->{$method}($request->input('requestId'));
                }

                break;
        }

        return response('Success', 200);
    }
}
