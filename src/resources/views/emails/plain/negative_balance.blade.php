{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.negativebalance-body', ['site' => $site]) !!}

{!! __('mail.negativebalance-body-ext', ['site' => $site]) !!}

{!! $walletUrl !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer', ['site' => $site]) !!}
