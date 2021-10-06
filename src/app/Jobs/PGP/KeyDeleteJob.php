<?php

namespace App\Jobs\PGP;

use App\Jobs\UserJob;

/**
 * Delete a GPG keypair for a user (or alias) from the DNS and Enigma storage.
 */
class KeyDeleteJob extends UserJob
{
    /**
     * Create a new job instance.
     *
     * @param int    $userId    User identifier.
     * @param string $userEmail User email address of the key
     *
     * @return void
     */
    public function __construct(int $userId, string $userEmail)
    {
        $this->userId = $userId;
        $this->userEmail = $userEmail;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handle()
    {
        $user = $this->getUser();

        if (!$user) {
            return;
        }

        \App\Backends\PGP::keyDelete($user, $this->userEmail);
    }
}
