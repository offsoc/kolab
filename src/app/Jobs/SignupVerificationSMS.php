<?php

namespace App\Jobs;

use App\SignupCode;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SignupVerificationSMS implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 2;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

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
        // TODO
    }
}
