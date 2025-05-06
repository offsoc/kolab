<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\SignupVerification;
use App\SignupCode;

class SignupVerificationJob extends MailJob
{
    /** @var SignupCode Signup verification code object */
    protected $code;

    /**
     * Create a new job instance.
     *
     * @param SignupCode $code Verification code object
     */
    public function __construct(SignupCode $code)
    {
        $this->code = $code;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Helper::sendMail(
            new SignupVerification($this->code),
            $this->code->tenant_id,
            ['to' => $this->code->email]
        );
    }
}
