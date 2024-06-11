<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', $vars) }}</p>

        <p>{{ __('mail.paymentmandatedisabled-body', $vars) }}</p>
        <p>{{ __('mail.paymentmandatedisabled-body-ext', $vars) }}</p>
        <p><a href="{{ $walletUrl }}">{{ $walletUrl }}</a></p>
        <p>{{ __('mail.paymentfailure-body-rest', $vars) }}</p>

@if ($supportUrl)
        <p>{{ __('mail.support', $vars) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>
@endif

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
