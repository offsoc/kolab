<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;

class VerifyJob extends UserJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = $this->getUser();

        // sanity checks
        if (!$user->hasSku('mailbox')) {
            $this->fail(new \Exception("User {$this->userId} has no mailbox SKU."));
        }

        // the user has a mailbox (or is marked as such)
        if ($user->isImapReady()) {
            $this->fail(new \Exception("User {$this->userId} is already verified."));
        }

        if (\App\Backends\IMAP::verifyAccount($user->email)) {
            $user->status |= \App\User::STATUS_IMAP_READY;
            $user->status |= \App\User::STATUS_ACTIVE;
            $user->save();
        }
    }
}
