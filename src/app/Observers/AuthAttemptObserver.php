<?php

namespace App\Observers;

use App\AuthAttempt;

/**
 * This is an observer for the AuthAttempt model definition.
 */
class AuthAttemptObserver
{
    /**
     * Handle the "creating" event on an AuthAttempt.
     *
     * Ensures that the entry uses a custom ID (uuid).
     *
     * @param AuthAttempt $authAttempt The AuthAttempt being created.
     *
     * @return void
     */
    public function creating(AuthAttempt $authAttempt)
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!AuthAttempt::find($allegedly_unique)) {
                $authAttempt->{$authAttempt->getKeyName()} = $allegedly_unique;
                break;
            }
        }
    }
}
