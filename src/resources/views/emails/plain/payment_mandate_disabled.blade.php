{!! __('mail.header', $vars) !!}

{!! __('mail.paymentmandatedisabled-body', $vars) !!}

{!! __('mail.paymentmandatedisabled-body-ext', $vars) !!}

{!! $walletUrl !!}

{!! __('mail.paymentfailure-body-rest', $vars) !!}

@if ($supportUrl)
{!! __('mail.support', $vars) !!}

{!! $supportUrl !!}
@endif

--
{!! __('mail.footer1', $vars) !!}
{!! __('mail.footer2', $vars) !!}
