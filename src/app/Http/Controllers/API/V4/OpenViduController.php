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
     * Listing of rooms that belong to the current user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::guard()->user();

        $rooms = Room::where('user_id', $user->id)->orderBy('name')->get();

        if (count($rooms) == 0) {
            // Create a room for the user
            list($name, $domain) = explode('@', $user->email);

            // Room name is limited to 16 characters by the DB schema
            if (strlen($name) > 16) {
                $name = substr($name, 0, 16);
            }

            while (Room::where('name', $name)->first()) {
                $name = \App\Utils::randStr(8);
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

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.roomnotfound'));
        }

        $user = Auth::guard()->user();

        // There's no existing session
        if (!$room->hasSession()) {
            // Participants can't join the room until the session is created by the owner
            if (!$user || $user->id != $room->user_id) {
                return $this->errorResponse(423, \trans('meet.sessionnotfound'));
            }

            // The room owner can create the session on request
            if (empty(request()->input('init'))) {
                return $this->errorResponse(424, \trans('meet.sessionnotfound'));
            }

            $session = $room->createSession();

            if (empty($session)) {
                return $this->errorResponse(500, \trans('meet.sessioncreateerror'));
            }
        }

        // Create session token for the current user/connection
        $response = $room->getSessionToken('PUBLISHER');

        if (empty($response)) {
            return $this->errorResponse(500, \trans('meet.sessionjoinerror'));
        }

        // Create session token for screen sharing connection
        if (!empty(request()->input('screenShare'))) {
            $add_token = $room->getSessionToken('PUBLISHER');

            $response['shareToken'] = $add_token['token'];
        }

        // Tell the UI who's the room owner
        $response['owner'] = $user && $user->id == $room->user_id;

        return response()->json($response, 200);
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
