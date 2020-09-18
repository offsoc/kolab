<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class Helper
{
    /**
     * Render the mail template.
     *
     * @param \Illuminate\Mail\Mailable $mail The mailable object
     * @param string $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function render(Mailable $mail, string $type = 'html'): string
    {
        // Plain text output
        if ($type == 'text') {
            $mail->build(); // @phpstan-ignore-line

            $mailer = \Illuminate\Container\Container::getInstance()->make('mailer');

            return $mailer->render(['text' => $mail->textView], $mail->buildViewData());
        } elseif ($type != 'html') {
            throw new \Exception("Unsupported output format");
        }

        // HTML output
        return $mail->build()->render(); // @phpstan-ignore-line
    }

    /**
     * Return user's email addresses, separately for use in To and Cc.
     *
     * @param \App\User $user     The user
     * @param bool      $external Include users's external email
     *
     * @return array To address as the first element, Cc address(es) as the second.
     */
    public static function userEmails(\App\User $user, bool $external = false): array
    {
        $to = $user->email;
        $cc = [];

        // If user has no mailbox entitlement we should not send
        // the email to his main address, but use external address, if defined
        if (!$user->hasSku('mailbox')) {
            $to = $user->getSetting('external_email');
        } elseif ($external) {
            $ext_email = $user->getSetting('external_email');

            if ($ext_email && $ext_email != $to) {
                $cc[] = $ext_email;
            }
        }

        return [$to, $cc];
    }
}
