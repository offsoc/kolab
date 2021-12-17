<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.degradedaccountreminder-body1', ['site' => $site]) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body2', ['site' => $site]) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body3', ['site' => $site]) }}</p>
        <p><a href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>
        <p>{{ __('mail.degradedaccountreminder-body4', ['site' => $site]) }}</p>
        <p>{{ __('mail.degradedaccountreminder-body5', ['site' => $site]) }}</p>

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
