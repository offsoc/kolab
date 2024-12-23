<?php

namespace App\Jobs;

use App\Mail\TrialEnd;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class TrialEndEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 30;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var \App\User The account owner */
    protected $account;

    /**
     * Create a new job instance.
     *
     * @param \App\User $account The account owner
     *
     * @return void
     */
    public function __construct(User $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //  Skip accounts that aren't ready for mail delivery
        if ($this->account->isLdapReady() && $this->account->isImapReady()) {
            \App\Mail\Helper::sendMail(
                new TrialEnd($this->account),
                $this->account->tenant_id,
                ['to' => $this->account->email]
            );
        }
    }
}
