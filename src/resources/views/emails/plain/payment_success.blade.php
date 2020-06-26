{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.paymentsuccess-body', ['site' => $site]) !!}

{!! $walletUrl !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}