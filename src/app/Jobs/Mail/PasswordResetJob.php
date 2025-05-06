<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\PasswordReset;
use App\VerificationCode;

class PasswordResetJob extends MailJob
{
    /** @var VerificationCode Verification code object */
    protected $code;

    /**
     * Create a new job instance.
     *
     * @param VerificationCode $code Verification code object
     */
    public function __construct(VerificationCode $code)
    {
        $this->code = $code;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $email = $this->code->user->getSetting('external_email');

        Helper::sendMail(
            new PasswordReset($this->code),
            $this->code->user->tenant_id,
            ['to' => $email]
        );
    }
}
