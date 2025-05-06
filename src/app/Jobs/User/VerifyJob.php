<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;
use App\Support\Facades\IMAP;
use App\User;

class VerifyJob extends UserJob
{
    /**
     * Execute the job.
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
            return;
        }

        if (IMAP::verifyAccount($user->email)) {
            $user->status |= User::STATUS_IMAP_READY;
            $user->status |= User::STATUS_ACTIVE;
            $user->save();
        }
    }
}
