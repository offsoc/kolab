<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use App\VerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class PasswordResetEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 2;

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
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        // FIXME: I think it does not make sense to continue trying after 1 hour
        return now()->addHours(1);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = $this->code->user->getSetting('external_email');

        Mail::to($email)->send(new PasswordReset($this->code));
    }
}
