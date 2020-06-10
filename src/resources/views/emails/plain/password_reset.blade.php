{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.passwordreset-body', ['code' => $short_code, 'site' => $site]) !!}

{!! $link !!}

-- 
{!! __('mail.footer', ['site' => $site]) !!}
