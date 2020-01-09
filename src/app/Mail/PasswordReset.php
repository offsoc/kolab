<?php

namespace App\Mail;

use App\VerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\VerificationCode A verification code object */
    protected $code;


    /**
     * Create a new message instance.
     *
     * @param \App\VerificationCode A verification code object
     *
     * @return void
     */
    public function __construct(VerificationCode $code)
    {
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $href = sprintf(
            '%s/login/reset/%s-%s',
            \config('app.url'),
            $this->code->short_code,
            $this->code->code
        );

        $this->view('emails.password_reset')
            ->subject(__('mail.passwordreset-subject', ['site' => \config('app.name')]))
            ->with([
                    'site' => \config('app.name'),
                    'code' => $this->code->code,
                    'short_code' => $this->code->short_code,
                    'link' => sprintf('<a href="%s">%s</a>', $href, $href),
                    'username' => $this->code->user->name
            ]);

        return $this;
    }
}
