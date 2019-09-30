<?php

namespace App\Mail;

use App\SignupCode;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupVerification extends Mailable
{
    use Queueable, SerializesModels;

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
        $this->view('emails.signup_code')
            ->subject(__('mail.signupcode-subject', ['site' => config('app.name')]))
            ->with([
                    'username' => $this->code->data['name'],
                    'code' => $this->code->code,
                    'short_code' => $this->code->short_code,
                    'url_code' => $this->code->short_code . '-' . $this->code->code,
            ]);

        return $this;
    }
}
