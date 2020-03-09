<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebsocketController extends Controller
{
    public function message($websocket, $data)
    {
        $websocket->emit("message", $data);
    }

    public function ping($websocket, $data)
    {
        $websocket->emit("pong", $data);
    }
}
