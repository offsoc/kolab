{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.negativebalancereminderdegrade-body', ['site' => $site]) !!}

{!! __('mail.negativebalancereminderdegrade-body-ext', ['site' => $site]) !!}

{!! $walletUrl !!}

{!! __('mail.negativebalancereminderdegrade-body-warning', ['site' => $site, 'date' => $date]) !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
