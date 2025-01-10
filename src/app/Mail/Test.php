<?php

namespace App\Mail;

class Test extends Mailable
{
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->text('emails.plain.test')
            ->subject("Kolab Email Test");

        return $this;
    }

    /**
     * Render the mail template with fake data
     *
     * @param string $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function fakeRender(string $type = 'text'): string
    {
        $mail = new self();

        return Helper::render($mail, $type);
    }
}
