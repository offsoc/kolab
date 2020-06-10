{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.paymentfailure-body', ['site' => $site]) !!}

{!! __('mail.paymentfailure-body-ext', ['site' => $site]) !!}

{!! $walletUrl !!}

{!! __('mail.paymentfailure-body-rest', ['site' => $site]) !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer', ['site' => $site]) !!}
