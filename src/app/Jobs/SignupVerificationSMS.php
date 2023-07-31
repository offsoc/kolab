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
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 10;

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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // TODO
    }
}
