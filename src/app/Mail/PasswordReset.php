<?php

namespace App\Mail;

use App\User;
use App\VerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PasswordReset extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\VerificationCode A verification code object */
    protected $code;


    /**
     * Create a new message instance.
     *
     * @param \App\VerificationCode $code A verification code object
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
                    'username' => $this->code->user->name(true)
            ]);

        return $this;
    }

    /**
     * Render the mail template with fake data
     *
     * @return string HTML output
     */
    public static function fakeRender(): string
    {
        $code = new VerificationCode([
                'code' => Str::random(VerificationCode::CODE_LENGTH),
                'short_code' => VerificationCode::generateShortCode(),
        ]);

        $code->user = new User([
              'email' => 'test@' . \config('app.domain'),
        ]);

        $mail = new self($code);

        return $mail->build()->render();
    }
}
