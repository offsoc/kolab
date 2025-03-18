<?php

namespace App\Jobs\Mail;

use App\Mail\SignupVerification;
use App\SignupCode;

class SignupVerificationJob extends \App\Jobs\MailJob
{
    /** @var SignupCode Signup verification code object */
    protected $code;


    /**
     * Create a new job instance.
     *
     * @param SignupCode $code Verification code object
     *
     * @return void
     */
    public function __construct(SignupCode $code)
    {
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \App\Mail\Helper::sendMail(
            new SignupVerification($this->code),
            $this->code->tenant_id,
            ['to' => $this->code->email]
        );
    }
}
