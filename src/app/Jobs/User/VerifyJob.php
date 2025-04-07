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
        $this->logJobStart($this->userEmail);

        $user = $this->getUser();

        if (!$user) {
            return;
        }

        if ($user->role) {
            // Admins/resellers don't reside in IMAP (for now)
            return;
        }

        // sanity checks
        if (!$user->hasSku('mailbox')) {
            $this->fail("User {$this->userId} has no mailbox SKU.");
            return;
        }

        // the user has a mailbox (or is marked as such)
        if ($user->isImapReady()) {
            $this->fail("User {$this->userId} is already verified.");
            return;
        }

        if (\App\Backends\IMAP::verifyAccount($user->email)) {
            $user->status |= \App\User::STATUS_IMAP_READY;
            $user->status |= \App\User::STATUS_ACTIVE;
            $user->save();
        }
    }
}
