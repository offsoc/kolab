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
    'footer1' => "Best regards,",
    'footer2' => "Your :site Team",

    'more-info-html' => "See <a href=\":href\">here</a> for more information.",
    'more-info-text' => "See :href for more information.",

    'negativebalance-subject' => ":site Payment Reminder",
    'negativebalance-body' => "It has probably skipped your attention that you are behind on paying for your :site account. "
        . "Consider setting up auto-payment to avoid messages like this in the future.",
    'negativebalance-body-ext' => "Settle up to keep your account running:",

    'passwordreset-subject' => ":site Password Reset",
    'passwordreset-body1' => "Someone recently asked to change your :site password.",
    'passwordreset-body2' => "If this was you, use this verification code to complete the process:",
    'passwordreset-body3' => "You can also click the link below:",
    'passwordreset-body4' => "If you did not make such a request, you can either ignore this message or get in touch with us about this incident.",

    'paymentmandatedisabled-subject' => ":site Auto-payment Problem",
    'paymentmandatedisabled-body' => "Your :site account balance is negative "
        . "and the configured amount for automatically topping up the balance does not cover "
        . "the costs of subscriptions consumed.",
    'paymentmandatedisabled-body-ext' => "Charging you multiple times for the same amount in short succession "
        . "could lead to issues with the payment provider. "
        . "In order to not cause any problems, we suspended auto-payment for your account. "
        . "To resolve this issue, login to your account settings and adjust your auto-payment amount.",

    'paymentfailure-subject' => ":site Payment Failed",
    'paymentfailure-body' => "Something went wrong with auto-payment for your :site account.\n"
        . "We tried to charge you via your preferred payment method, but the charge did not go through.",
    'paymentfailure-body-ext' => "In order to not cause any further issues, we suspended auto-payment for your account. "
        . "To resolve this issue, login to your account settings at",
    'paymentfailure-body-rest' => "There you can pay manually for your account and "
        . "change your auto-payment settings.",

    'paymentsuccess-subject' => ":site Payment Succeeded",
    'paymentsuccess-body' => "The auto-payment for your :site account went through without issues. "
        . "You can check your new account balance and more details here:",

    'support' => "Special circumstances? Something is wrong with a charge?\n"
        . ":site Support is here to help.",

    'signupcode-subject' => ":site Registration",
    'signupcode-body1' => "This is your verification code for the :site registration process:",
    'signupcode-body2' => "You can also click the link below to continue the registration process:",

    'suspendeddebtor-subject' => ":site Account Suspended",
    'suspendeddebtor-body' => "You have been behind on paying for your :site account "
        ."for over :days days. Your account has been suspended.",
    'suspendeddebtor-middle' => "Settle up now to reactivate your account.",
    'suspendeddebtor-cancel' => "Don't want to be our customer anymore? "
        . "Here is how you can cancel your account:",
];
