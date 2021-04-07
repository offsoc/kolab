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
        return;
    }
);

Websocket::on(
    'open',
    function ($websocket, Request $request) {
        return;
    }
);

Websocket::on(
    'disconnect',
    function ($websocket) {
        return;
    }
);

//Websocket::on('message', 'App\Http\Controllers\WebsocketController@message');
//Websocket::on('ping', 'App\Http\Controllers\WebsocketController@ping');
