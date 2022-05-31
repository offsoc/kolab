<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>
        <p>{{ __('mail.trialend-intro', ['site' => $site]) }}</p>
@if ($paymentUrl)
        <p>{{ __('mail.trialend-kb', ['site' => $site]) }}</p>
        <p><a href="{{ $paymentUrl }}">{{ $paymentUrl }}</a></p>
@endif
        <p>{{ __('mail.trialend-body1', ['site' => $site]) }}</p>
        <p>{{ __('mail.trialend-body2', ['site' => $site]) }}</p>
        <p>{{ __('mail.trialend-body3', ['site' => $site]) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
