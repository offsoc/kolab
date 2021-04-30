<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.signupinvitation-header') }}</p>

        <p>{{ __('mail.signupinvitation-body1', ['site' => $site]) }}</p>

        <p><a href="{!! $href !!}">{!! $href !!}</a></p>

        <p>{{ __('mail.signupinvitation-body2') }}</p>

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
