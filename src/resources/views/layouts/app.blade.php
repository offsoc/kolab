<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no, maximum-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        {{-- TODO: PWA disabled for now: @laravelPWA --}}
        <link rel="icon" type="image/x-icon" href="@theme_asset(images/favicon.ico)">
        <link href="@theme_asset(app.css)" rel="stylesheet">
    </head>
    <body>
        <div class="outer-container">
            @yield('content')
        </div>

        <script>window.config = {!! json_encode($env) !!}</script>
        <script src="{{ asset('js/' . $env['jsapp']) }}" defer></script>
    </body>
</html>
