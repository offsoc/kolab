<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.passwordreset-body1', ['site' => $site]) }} <br/> {{ __('mail.passwordreset-body2') }}</p>

        <p><strong>{!! $short_code !!}</strong></p>

        <p>{{ __('mail.passwordreset-body3') }}</p>

        <p>{!! $link !!}</p>

        <p>{{ __('mail.passwordreset-body4') }}</p>

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
