<?php

namespace App\Observers;

use App\Delegation;

class DelegationObserver
{
    /**
     * Handle the delegation "created" event.
     *
     * @param Delegation $delegation The delegation
     */
    public function created(Delegation $delegation): void
    {
        \App\Jobs\User\Delegation\CreateJob::dispatch($delegation->id);
    }

    /**
     * Handle the delegation "deleted" event.
     *
     * @param Delegation $delegation The delegation
     */
    public function deleted(Delegation $delegation): void
    {
        \App\Jobs\User\Delegation\DeleteJob::dispatch($delegation->user->email, $delegation->delegatee->email);
    }
}
