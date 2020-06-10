{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.signupcode-body', ['code' => $short_code, 'site' => $site]) !!}

{!! $href !!}

-- 
{!! __('mail.footer', ['site' => $site]) !!}
