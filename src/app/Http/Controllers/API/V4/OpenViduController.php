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

        $room = \App\OpenVidu\Room::where('session_id', $id);

        // see if room exists, return session and token
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => \config('openvidu.api_url'),
                'verify' => \config('openvidu.api_verify_tls')
            ]
        );

        $response = $client->request(
            'GET',
            "sessions/{$id}",
            ['auth' => [\config('openvidu.api_username'), \config('openvidu.api_password')]]
        );

        $sessionExists = $response->getStatusCode() == 200;

        if (!$sessionExists) {
            if ($room->user_id == $user) {
                $json = [
                    'mediaMode' => 'ROUTED',
                    'recordingMode' => 'MANUAL',
                    'customSessionId' => $room->session_id
                ];

                $response = $client->request(
                    'POST',
                    'sessions',
                    [
                        'auth' => [
                            \config('openvidu.api_username'),
                            \config('openvidu.api_password')
                        ],
                        'json' => [
                            'mediaMode' => 'ROUTED',
                            'recordingMode' => 'MANUAL',
                            'customSessionId' => $room->session_id
                        ]
                    ]
                );

                if ($response->getResponseCode() !== 200) {
                    return response()->json(['status' => 'error'], 422);
                }

                $response = $client->request(
                    'POST',
                    'tokens',
                    [
                        'auth' => [
                            \config('openvidu.api_username'),
                            \config('openvidu.api_password')
                        ],
                        'json' => [
                            'session' => $room->session_id,
                            'role' => 'MODERATOR'
                        ]
                    ]
                );

                $json = json_decode($response->getBody(), true);

                return response()->json($json, 200);
            } else {
                return response()->json(['status' => 'waiting'], 422);
            }
        }

        $response = $client->request(
            'POST',
            'tokens',
            [
                'auth' => [
                    \config('openvidu.api_username'),
                    \config('openvidu.api_password')
                ],
                'json' => [
                    'session' => $room->session_id,
                    'role' => 'MODERATOR'
                ]
            ]
        );

        $json = json_decode($response->getBody(), true);

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
