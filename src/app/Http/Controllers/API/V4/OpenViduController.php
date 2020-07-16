<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OpenViduController extends Controller
{
    /**
     * Join or create the room. Each room has one owner, and the room isn't open until the owner
     * joins (and effectively creates the session.
     */
    public function joinOrCreate($id)
    {
        $user = Auth::guard()->user();

        $room = \App\OpenVidu\Room::where('name', $id)->first();

        // This isn't a room, bye bye
        if (!$room) {
            return $this->errorResponse(404, \trans('meet.roomnotfound'));
        }

        // There's no existing session
        if (!$room->hasSession()) {
            // Only the room owner can create the session
            if ($user->id != $room->user_id) {
                return $this->errorResponse(423, \trans('meet.sessionnotfound'));
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

        if (!empty(request()->input('screenShare'))) {
            $add_token = $room->getSessionToken('PUBLISHER');

            $response['shareToken'] = $add_token['token'];
        }

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
        return response('Success', 200);
    }
}
