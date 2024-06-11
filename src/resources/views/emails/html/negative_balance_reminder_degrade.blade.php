<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', $vars) }}</p>

        <p>{{ __('mail.negativebalancereminderdegrade-body', $vars) }}</p>
        <p>{{ __('mail.negativebalancereminderdegrade-body-ext', $vars) }}</p>
        <p><a href="{{ $walletUrl }}">{{ $walletUrl }}</a></p>
        <p><b>{{ __('mail.negativebalancereminderdegrade-body-warning', $vars) }}</b></p>

@if ($supportUrl)
        <p>{{ __('mail.support', $vars) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>
@endif

        <p>{{ __('mail.footer1', $vars) }}</p>
        <p>{{ __('mail.footer2', $vars) }}</p>
    </body>
</html>
