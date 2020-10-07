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

        \App\Backends\LDAP::deleteUser($user);

        $user->status |= \App\User::STATUS_DELETED;
        $user->save();
    }
}
