<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.signupinvitation-header', $vars) }}</p>

        <p>{{ __('mail.signupinvitation-body1', $vars) }}</p>

        <p><a href="{!! $href !!}">{!! $href !!}</a></p>

        <p>{{ __('mail.signupinvitation-body2', $vars) }}</p>

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
