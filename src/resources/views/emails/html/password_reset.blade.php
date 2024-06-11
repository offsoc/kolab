<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', $vars) }}</p>

        <p>{{ __('mail.passwordreset-body1', $vars) }} <br/> {{ __('mail.passwordreset-body2', $vars) }}</p>

        <p><strong>{!! $short_code !!}</strong></p>

        <p>{{ __('mail.passwordreset-body3', $vars) }}</p>

        <p>{!! $link !!}</p>

        <p>{{ __('mail.passwordreset-body4', $vars) }}</p>

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
