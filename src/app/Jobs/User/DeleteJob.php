<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;

class DeleteJob extends UserJob
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
        if ($user->isDeleted()) {
            $this->fail(new \Exception("User {$this->userId} is already marked as deleted."));
        }

        LDAP::deleteUser($user);

        $user->status |= User::STATUS_DELETED;
        $user->save();
    }
}
