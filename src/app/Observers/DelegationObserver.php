<?php

namespace App\Observers;

use App\Delegation;
use App\Jobs\User\Delegation\CreateJob;
use App\Jobs\User\Delegation\DeleteJob;

class DelegationObserver
{
    /**
     * Handle the delegation "created" event.
     *
     * @param Delegation $delegation The delegation
     */
    public function created(Delegation $delegation): void
    {
        CreateJob::dispatch($delegation->id);
    }

    /**
     * Handle the delegation "deleted" event.
     *
     * @param Delegation $delegation The delegation
     */
    public function deleted(Delegation $delegation): void
    {
        DeleteJob::dispatch($delegation->user->email, $delegation->delegatee->email);
    }
}
