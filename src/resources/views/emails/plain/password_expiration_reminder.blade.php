{!! __('mail.header', ['name' => $username]) !!}

{!! __('mail.passwordexpiration-body', ['site' => $site, 'date' => $date]) !!}

{!! $link !!}

-- 
{!! __('mail.footer1') !!}
{!! __('mail.footer2', ['site' => $site]) !!}
