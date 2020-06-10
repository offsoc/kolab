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
}
