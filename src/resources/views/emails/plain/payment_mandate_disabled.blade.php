{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.paymentmandatedisabled-body', ['site' => $site]) !!}

{!! __('mail.paymentmandatedisabled-body-ext', ['site' => $site]) !!}

{!! $walletUrl !!}

{!! __('mail.paymentfailure-body-rest', ['site' => $site]) !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
