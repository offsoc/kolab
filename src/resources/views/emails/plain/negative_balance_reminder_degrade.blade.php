{!! __('mail.header', $vars) !!}

{!! __('mail.negativebalancereminderdegrade-body', $vars) !!}

{!! __('mail.negativebalancereminderdegrade-body-ext', $vars) !!}

{!! $walletUrl !!}

{!! __('mail.negativebalancereminderdegrade-body-warning', $vars) !!}

@if ($supportUrl)
{!! __('mail.support', $vars) !!}

{!! $supportUrl !!}
@endif

--
{!! __('mail.footer1', $vars) !!}
{!! __('mail.footer2', $vars) !!}
