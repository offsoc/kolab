{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.suspendeddebtor-body', ['site' => $site, 'days' => $days]) !!} {!! $moreInfoText !!}

{!! __('mail.suspendeddebtor-middle') !!}

{!! $walletUrl !!}

@if ($supportUrl)
{!! __('mail.support', ['site' => $site]) !!}

{!! $supportUrl !!}
@endif
@if ($cancelUrl)

{!! __('mail.suspendeddebtor-cancel') !!}

{!! $cancelUrl !!}
@endif

-- 
{!! __('mail.footer', ['site' => $site]) !!}
