<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use App\VerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class PasswordResetEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 10;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

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
