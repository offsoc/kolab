<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OpenViduController extends Controller
{
    /**
     * Join or create the room. Each room as one owner, and the room isn't open until the owner
     * joins (and effectively creates the session.
     */
    public function joinOrCreate($id)
    {
        $user = Auth::guard()->user();

        $room = \App\OpenVidu\Room::where('name', $id)->first();

        // this isn't a room, bye bye
        if (!$room) {
            return response()->json(['status' => 'error'], 404);
        }

        // there's no existing session
        if (!$room->hasSession()) {
            // TODO: only the room owner should be able to create the session
            $room->createSession();
        }

        $json = $room->getSessionToken('PUBLISHER');

        return response()->json($json, 200);
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
