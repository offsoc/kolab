<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', $vars) }}</p>
        <p>{{ __('mail.trialend-intro', $vars) }}</p>
@if ($paymentUrl)
        <p>{{ __('mail.trialend-kb', $vars) }}</p>
        <p><a href="{{ $paymentUrl }}">{{ $paymentUrl }}</a></p>
@endif
        <p>{{ __('mail.trialend-body1', $vars) }}</p>
        <p>{{ __('mail.trialend-body2', $vars) }}</p>
        <p>{{ __('mail.trialend-body3', $vars) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
