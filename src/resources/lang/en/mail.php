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

    'passwordreset-subject' => ":site Password Reset",
    'passwordreset-body' => "Someone recently asked to change your :site password.\n"
        . "If this was you, use this verification code to complete the process: :code.\n"
        . "You can also click the link below.\n"
        . "If you did not make such a request, you can either ignore this message or get in touch with us about this incident.",

    'signupcode-subject' => ":site Registration",
    'signupcode-body' => "This is your verification code for the :site registration process: :code.\n"
        . "You can also click the link below to continue the registration process:",
];
