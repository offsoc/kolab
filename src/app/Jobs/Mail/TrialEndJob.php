<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\TrialEnd;
use App\User;

class TrialEndJob extends MailJob
{
    /** @var User The account owner */
    protected $account;

    /**
     * Create a new job instance.
     *
     * @param User $account The account owner
     */
    public function __construct(User $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        //  Skip accounts that aren't ready for mail delivery
        if ($this->account->isLdapReady() && $this->account->isImapReady()) {
            Helper::sendMail(
                new TrialEnd($this->account),
                $this->account->tenant_id,
                ['to' => $this->account->email]
            );
        }
    }
}
