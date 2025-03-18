<?php

namespace App\Jobs\Mail;

use App\Mail\TrialEnd;
use App\User;

class TrialEndJob extends \App\Jobs\MailJob
{
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
