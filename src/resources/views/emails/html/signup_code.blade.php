<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.signupcode-body1', ['site' => $site]) }}</p>

        <p><strong>{!! $short_code !!}</strong></p>

        <p>{{ __('mail.signupcode-body2') }}</p>

        <p><a href="{!! $href !!}">{!! $href !!}</a></p>

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
