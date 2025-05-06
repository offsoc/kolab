<?php

namespace App\Jobs\User\Delegation;

use App\Jobs\UserJob;
use App\Support\Facades\Roundcube;

class UserRefreshJob extends UserJob
{
    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle()
    {
        $this->logJobStart("{$this->userId} ({$this->userEmail})");

        if (!\config('database.connections.roundcube')) {
            return;
        }

        $user = $this->getUser();

        if (!$user || $user->trashed()) {
            return;
        }

        // Delete delegators' identities in Roundcube
        Roundcube::resetIdentities($user);
    }
}
