{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.trialend-intro', ['site' => $site]) !!}
@if ($paymentUrl)

{!! __('mail.trialend-kb', ['site' => $site]) !!} {!! $paymentUrl !!}
@endif

{!! __('mail.trialend-body1', ['site' => $site]) !!}

{!! __('mail.trialend-body2', ['site' => $site]) !!}

{!! __('mail.trialend-body3', ['site' => $site]) !!}

{!! $supportUrl !!}

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
