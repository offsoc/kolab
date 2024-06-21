<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', $vars) }}</p>

        <p>{{ __('mail.degradedaccountreminder-body1', $vars) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body2', $vars) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body3', $vars) }}</p>
        <p><a href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>
        <p>{{ __('mail.degradedaccountreminder-body4', $vars) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body5', $vars) }}</p>

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
