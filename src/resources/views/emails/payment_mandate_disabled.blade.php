<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.paymentmandatedisabled-body', ['site' => $site]) }}</p>
        <p><a href="{{ $walletUrl }}">{{ $walletUrl }}</a></p>
        <p>{{ __('mail.paymentfailure-body-rest', ['site' => $site]) }}</p>

@if ($supportUrl)
        <p>{{ __('mail.support', ['site' => $site]) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>
@endif

        <p>{{ __('mail.footer', ['site' => $site, 'appurl' => config('app.url')]) }}</p>
    </body>
</html>
