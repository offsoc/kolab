{!! __('mail.header', $vars) !!}

{!! __('mail.trialend-intro', $vars) !!}
@if ($paymentUrl)

{!! __('mail.trialend-kb', $vars) !!} {!! $paymentUrl !!}
@endif

{!! __('mail.trialend-body1', $vars) !!}

{!! __('mail.trialend-body2', $vars) !!}

{!! __('mail.trialend-body3', $vars) !!}

{!! $supportUrl !!}

-- 
{!! __('mail.footer1', $vars) !!}
{!! __('mail.footer2', $vars) !!}
