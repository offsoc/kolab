<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{{ __('mail.header', ['name' => $username]) }}</p>

        <p>{{ __('mail.negativebalancereminder-body', ['site' => $site]) }}</p>
        <p>{{ __('mail.negativebalancereminder-body-ext', ['site' => $site]) }}</p>
        <p><a href="{{ $walletUrl }}">{{ $walletUrl }}</a></p>
        <p><b>{{ __('mail.negativebalancereminderdegrade-body-warning', ['site' => $site, 'date' => $date]) }}</b></p>

@if ($supportUrl)
        <p>{{ __('mail.support', ['site' => $site]) }}</p>
        <p><a href="{{ $supportUrl }}">{{ $supportUrl }}</a></p>
@endif

        <p>{{ __('mail.footer1') }}</p>
        <p>{{ __('mail.footer2', ['site' => $site]) }}</p>
    </body>
</html>
