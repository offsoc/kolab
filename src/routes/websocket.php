<?php

use Illuminate\Http\Request;
use SwooleTW\Http\Websocket\Facades\Websocket;

/*
|--------------------------------------------------------------------------
| Websocket Routes
|--------------------------------------------------------------------------
|
| Here is where you can register websocket events for your application.
|
*/

Websocket::on(
    'connect',
    function ($websocket, Request $request) {
        \Log::debug("someone connected");
        $websocket->emit(
            'message',
            'welcome'
        );
    }
);

Websocket::on(
    'open',
    function ($websocket, Request $request) {
        \Log::debug("socket opened");
    }
);

Websocket::on(
    'disconnect',
    function ($websocket) {
        \Log::debug("someone disconnected");
    }
);

Websocket::on('message', 'App\Http\Controllers\WebsocketController@message');
Websocket::on('ping', 'App\Http\Controllers\WebsocketController@ping');
