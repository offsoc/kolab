<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in mail/sms messages sent by the app.
    */

    'header' => "Dear :name,",
    'footer' => "Best regards,\nYour :site Team",

    'negativebalance-subject' => ":site Payment Reminder",
    'negativebalance-body' => "It has probably skipped your attention that you are behind on paying for your :site account. "
        . "Consider setting up auto-payment to avoid messages like this in the future.\n\n"
        . "Settle up to keep your account running.",

    'passwordreset-subject' => ":site Password Reset",
    'passwordreset-body' => "Someone recently asked to change your :site password.\n"
        . "If this was you, use this verification code to complete the process: :code.\n"
        . "You can also click the link below.\n"
        . "If you did not make such a request, you can either ignore this message or get in touch with us about this incident.",

    'signupcode-subject' => ":site Registration",
    'signupcode-body' => "This is your verification code for the :site registration process: :code.\n"
        . "You can also click the link below to continue the registration process:",

    'suspendeddebtor-subject' => ":site Account Suspended",
    'suspendeddebtor-body' => "You have been behind on paying for your :site account "
        ."for over :days days. Your account has been suspended.",
    'suspendeddebtor-middle' => "Settle up now to reactivate your account.",
    'suspendeddebtor-cancel' => "Don't want to be our customer anymore? "
        . "Here is how you can cancel your account:",

    'support' => "Special circumstances? Something wrong with a charge?\n"
        . " :site Support is here to help:",

    'more-info-html' => "See <a href=\":href\">here</a> for more information.",
];
