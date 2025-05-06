{!! __('mail.header', $vars) !!}

{!! __('mail.negativebalance-body', $vars) !!}

{!! __('mail.negativebalance-body-ext', $vars) !!}

{!! $walletUrl !!}

@if ($supportUrl)
{!! __('mail.support', $vars) !!}

{!! $supportUrl !!}
@endif

--
{!! __('mail.footer1', $vars) !!}
{!! __('mail.footer2', $vars) !!}
