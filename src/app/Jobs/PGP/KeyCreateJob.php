<?php

namespace App\Jobs\PGP;

use App\Jobs\UserJob;

/**
 * Create a GPG keypair for a user (or alias).
 *
 * Throws exceptions for the following reasons:
 *
 *   * The user is marked as deleted (`$user->isDeleted()`), or
 *   * the user is actually deleted (`$user->deleted_at`)
 *   * the alias is actually deleted
 *   * there was an error in keypair generation process
 */
class KeyCreateJob extends UserJob
{
    /**
     * Create a new job instance.
     *
     * @param int    $userId    User identifier.
     * @param string $userEmail User email address for the key
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

        // sanity checks
        if ($user->isDeleted()) {
            $this->fail("User {$this->userId} is marked as deleted.");
            return;
        }

        if ($user->trashed()) {
            $this->fail("User {$this->userId} is actually deleted.");
            return;
        }

        if (
            $this->userEmail != $user->email
            && !$user->aliases()->where('alias', $this->userEmail)->exists()
        ) {
            $this->fail("Alias {$this->userEmail} is actually deleted.");
            return;
        }

        \App\Backends\PGP::keypairCreate($user, $this->userEmail);
    }
}
