<?php

namespace App\Observers;

use App\SignupInvitation as SI;

/**
 * This is an observer for the SignupInvitation model definition.
 */
class SignupInvitationObserver
{
    /**
     * Ensure the invitation ID is a custom ID (uuid).
     *
     * @param \App\SignupInvitation $invitation The invitation object
     *
     * @return void
     */
    public function creating(SI $invitation)
    {
        $invitation->status = SI::STATUS_NEW;

        $invitation->tenant_id = \config('app.tenant_id');
    }

    /**
     * Handle the invitation "created" event.
     *
     * @param \App\SignupInvitation $invitation The invitation object
     *
     * @return void
     */
    public function created(SI $invitation)
    {
        \App\Jobs\SignupInvitationEmail::dispatch($invitation);
    }

    /**
     * Handle the invitation "updated" event.
     *
     * @param \App\SignupInvitation $invitation The invitation object
     *
     * @return void
     */
    public function updated(SI $invitation)
    {
        $oldStatus = $invitation->getOriginal('status');

        // Resend the invitation
        if (
            $invitation->status == SI::STATUS_NEW
            && ($oldStatus == SI::STATUS_FAILED || $oldStatus == SI::STATUS_SENT)
        ) {
            \App\Jobs\SignupInvitationEmail::dispatch($invitation);
        }
    }
}
