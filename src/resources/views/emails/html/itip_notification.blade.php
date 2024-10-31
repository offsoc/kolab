<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>{!! $vars['body1'] !!}</p>
@if ($vars['body2'])
        <p>{!! $vars['body2'] !!}</p>
@endif
@if ($vars['comment'])
        <p>{!! __('mail.itip-comment', $vars) !!}</p>
@endif
@if ($vars['recurrenceId'])
        <p>{!! __('mail.itip-recurrence-note') !!}</p>
@endif

        <p><i>*** {!! __('mail.itip-footer') !!} ***</i><p>
    </body>
</html>
