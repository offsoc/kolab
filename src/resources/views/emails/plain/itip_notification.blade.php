{!! $vars['body1'] !!}
@if ($vars['body2'])

{!! $vars['body2'] !!}
@endif
@if ($vars['comment'])

{!! __('mail.itip-comment', $vars) !!}
@endif
@if ($vars['recurrenceId'])

{!! __('mail.itip-recurrence-note') !!}
@endif


*** {!! __('mail.itip-footer') !!} ***
