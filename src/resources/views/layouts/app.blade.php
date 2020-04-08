<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }} -- @yield('title')</title>

        {{-- TODO: PWA disabled for now: @laravelPWA --}}
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    </head>
    <body>
        <div class="outer-container">
            @yield('content')
        </div>

        <script>window.config = {!! json_encode($env) !!}</script>
        <script src="{{ asset('js/' . $env['jsapp']) }}" defer></script>
    </body>
</html>
