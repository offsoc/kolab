<?php

namespace Tests;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use KirschbaumDevelopment\MailIntercept\WithMailInterceptor;

trait MailInterceptTrait
{
    use WithMailInterceptor;

    /**
     * Extract content of a email message.
     *
     * @param \Illuminate\Mail\Mailable $mail Mailable object
     *
     * @return array Parsed message data:
     *               - 'plain': Plain text body
     *               - 'html: HTML body
     *               - 'message': Swift_Message object
     */
    protected function fakeMail(Mailable $mail): array
    {
        $this->interceptMail();

        Mail::send($mail);

        $message = $this->interceptedMail()->first();

        // SwiftMailer does not have methods to get the bodies, we'll parse the message
        list($plain, $html) = $this->extractMailBody($message->toString());

        return [
            'plain' => $plain,
            'html' => $html,
            'message' => $message,
        ];
    }

    /**
     * Simple message parser to extract plain and html body
     *
     * @param string $message Email message as string
     *
     * @return array Plain text and HTML body
     */
    protected function extractMailBody(string $message): array
    {
        // Note that we're not supporting every message format, we only
        // support what Laravel/SwiftMailer produces
        // TODO: It may stop working if we start using attachments
        $plain = '';
        $html = '';

        if (preg_match('/[\s\t]boundary="([^"]+)"/', $message, $matches)) {
            // multipart message assume plain and html parts
            $split = preg_split('/--' . preg_quote($matches[1]) . '/', $message);

            list($plain_head, $plain) = explode("\r\n\r\n", $split[1], 2);
            list($html_head, $html) = explode("\r\n\r\n", $split[2], 2);

            if (strpos($plain_head, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                $plain = quoted_printable_decode($plain);
            }

            if (strpos($html_head, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                $html = quoted_printable_decode($html);
            }
        } else {
            list($header, $html) = explode("\r\n\r\n", $message, 2);
            if (strpos($header, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                $html = quoted_printable_decode($html);
            }
        }

        return [$plain, $html];
    }
}
