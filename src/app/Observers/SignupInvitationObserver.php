<?php

namespace App\Observers;

use App\Jobs\Mail\SignupInvitationJob;
use App\SignupInvitation;
use App\SignupInvitation as SI;

/**
 * This is an observer for the SignupInvitation model definition.
 */
class SignupInvitationObserver
{
    /**
     * Ensure the invitation ID is a custom ID (uuid).
     *
     * @param SignupInvitation $invitation The invitation object
     */
    public function creating(SI $invitation)
    {
        $invitation->status = SI::STATUS_NEW;
    }

    /**
     * Handle the invitation "created" event.
     *
     * @param SignupInvitation $invitation The invitation object
     */
    public function created(SI $invitation)
    {
        SignupInvitationJob::dispatch($invitation);
    }

    /**
     * Handle the invitation "updated" event.
     *
     * @param SignupInvitation $invitation The invitation object
     */
    public function updated(SI $invitation)
    {
        $oldStatus = $invitation->getOriginal('status');

        // Resend the invitation
        if (
            $invitation->status == SI::STATUS_NEW
            && ($oldStatus == SI::STATUS_FAILED || $oldStatus == SI::STATUS_SENT)
        ) {
            SignupInvitationJob::dispatch($invitation);
        }
    }
}
