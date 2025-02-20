<?php

namespace App\Jobs\Mail;

use App\Mail\PasswordReset;
use App\VerificationCode;

class PasswordResetJob extends \App\Jobs\MailJob
{
    /** @var \App\VerificationCode Verification code object */
    protected $code;


    /**
     * Create a new job instance.
     *
     * @param \App\VerificationCode $code Verification code object
     *
     * @return void
     */
    public function __construct(VerificationCode $code)
    {
        $this->code = $code;
        $this->onQueue(self::QUEUE);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = $this->code->user->getSetting('external_email');

        \App\Mail\Helper::sendMail(
            new PasswordReset($this->code),
            $this->code->user->tenant_id,
            ['to' => $email]
        );
    }
}
