{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.signupcode-body1', ['site' => $site]) !!}

{!! $short_code !!}

{!! __('mail.signupcode-body2') !!}

{!! $href !!}

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
