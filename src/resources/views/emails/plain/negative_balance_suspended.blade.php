{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.negativebalancesuspended-body', ['site' => $site]) !!}

{!! __('mail.negativebalancesuspended-body-ext', ['site' => $site]) !!}

{!! $walletUrl !!}

{!! __('mail.negativebalancesuspended-body-warning', ['site' => $site, 'date' => $date]) !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
