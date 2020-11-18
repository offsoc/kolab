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
     * Close the room session.
     *
     * @param string $id Room identifier (name)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeRoom($id)
    {
        $room = Room::where('name', $id)->first();

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        $user = Auth::guard()->user();

        // Only the room owner can do it
        if (!$user || $user->id != $room->user_id) {
            return $this->errorResponse(403);
        }

        if (!$room->deleteSession()) {
            return $this->errorResponse(500, \trans('meet.session-close-error'));
        }

        return response()->json([
                'status' => 'success',
                'message' => __('meet.session-close-success'),
        ]);
    }

    /**
     * Listing of rooms that belong to the current user.
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

        // Check if there's still a valid beta entitlement for the room owner
        $sku = \App\Sku::where('title', 'meet')->first();
        if ($sku && !$room->owner->entitlements()->where('sku_id', $sku->id)->first()) {
            return $this->errorResponse(404, \trans('meet.room-not-found'));
        }

        $user = Auth::guard()->user();

        // There's no existing session
        if (!$room->hasSession()) {
            // Participants can't join the room until the session is created by the owner
            if (!$user || $user->id != $room->user_id) {
                return $this->errorResponse(423, \trans('meet.session-not-found'));
            }

            // The room owner can create the session on request
            if (empty(request()->input('init'))) {
                return $this->errorResponse(424, \trans('meet.session-not-found'));
            }

            $session = $room->createSession();

            if (empty($session)) {
                return $this->errorResponse(500, \trans('meet.session-create-error'));
            }
        }

        // Create session token for the current user/connection
        $response = $room->getSessionToken('PUBLISHER');

        if (empty($response)) {
            return $this->errorResponse(500, \trans('meet.session-join-error'));
        }

        // Create session token for screen sharing connection
        if (!empty(request()->input('screenShare'))) {
            $add_token = $room->getSessionToken('PUBLISHER');

            $response['shareToken'] = $add_token['token'];
        }

        // Tell the UI who's the room owner
        $response['owner'] = $user && $user->id == $room->user_id;

        return response()->json($response);
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

                break;
        }

        return response('Success', 200);
    }
}
