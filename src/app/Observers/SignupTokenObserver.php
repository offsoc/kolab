<?php

namespace App\Observers;

use App\SignupToken;

class SignupTokenObserver
{
    /**
     * Ensure the token is uppercased.
     *
     * @param \App\SignupToken $token The SignupToken object
     */
    public function creating(SignupToken $token): void
    {
        $token->id = strtoupper(trim($token->id));
    }
}
