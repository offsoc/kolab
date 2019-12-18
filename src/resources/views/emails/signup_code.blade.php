<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.signupcode-body', ['code' => $short_code, 'site' => $site]) }}</p>

        <p><a src="{{ config('app.url') }}/signup/{{ $url_code }}">{{ config('app.url') }}/signup/{{ $url_code }}</a></p>

        <p>{{ __('mail.footer', ['site' => $site, 'appurl' => config('app.url')]) }}</p>
    </body>
</html>
