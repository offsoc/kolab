<?php

namespace App\Jobs\PGP;

use App\Jobs\UserJob;
use App\Support\Facades\PGP;

/**
 * Delete a GPG keypair for a user (or alias) from the DNS and Enigma storage.
 */
class KeyDeleteJob extends UserJob
{
    /**
     * Create a new job instance.
     *
     * @param int    $userId    user identifier
     * @param string $userEmail User email address of the key
     */
    public function __construct(int $userId, string $userEmail)
    {
        $this->userId = $userId;
        $this->userEmail = $userEmail;
    }

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle()
    {
        $user = $this->getUser();

        if (!$user) {
            return;
        }

        PGP::keyDelete($user, $this->userEmail);
    }
}
