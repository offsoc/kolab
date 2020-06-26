{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.passwordreset-body1', ['site' => $site]) !!}
{!! __('mail.passwordreset-body2') !!}

{!! $short_code !!}

{!! __('mail.passwordreset-body3') !!}

{!! $link !!}

{!! __('mail.passwordreset-body4') !!}

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
