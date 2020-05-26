<?php

namespace App\Mail;

use App\SignupCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupVerification extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var SignupCode A signup verification code object */
    protected $code;


    /**
     * Create a new message instance.
     *
     * @param SignupCode $code A signup verification code object
     *
     * @return void
     */
    public function __construct(SignupCode $code)
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
            '%s/signup/%s-%s',
            \config('app.url'),
            $this->code->short_code,
            $this->code->code
        );

        $username = $this->code->data['first_name'] ?? '';
        if (!empty($this->code->data['last_name'])) {
            $username .= ' ' . $this->code->data['last_name'];
        }
        $username = trim($username);

        $this->view('emails.signup_code')
            ->subject(__('mail.signupcode-subject', ['site' => \config('app.name')]))
            ->with([
                    'site' => \config('app.name'),
                    'username' => $username ?: 'User',
                    'code' => $this->code->code,
                    'short_code' => $this->code->short_code,
                    'link' => sprintf('<a href="%s">%s</a>', $href, $href),
            ]);

        return $this;
    }
}
