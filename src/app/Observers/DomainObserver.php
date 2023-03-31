<?php

namespace App\Observers;

use App\Domain;
use Illuminate\Support\Facades\DB;

class DomainObserver
{
    /**
     * Handle the domain "created" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function creating(Domain $domain): void
    {
        $domain->namespace = \strtolower($domain->namespace);

        $domain->status |= Domain::STATUS_NEW;
    }

    /**
     * Handle the domain "created" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function created(Domain $domain)
    {
        // Create domain record in LDAP
        // Note: DomainCreate job will dispatch DomainVerify job
        \App\Jobs\Domain\CreateJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "deleted" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function deleted(Domain $domain)
    {
        if ($domain->isForceDeleting()) {
            return;
        }

        \App\Jobs\Domain\DeleteJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "deleting" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function deleting(Domain $domain)
    {
        \App\Policy\RateLimitWhitelist::where(
            [
                'whitelistable_id' => $domain->id,
                'whitelistable_type' => Domain::class
            ]
        )->delete();
    }

    /**
     * Handle the domain "updated" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function updated(Domain $domain)
    {
        \App\Jobs\Domain\UpdateJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "restoring" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function restoring(Domain $domain)
    {
        // Reset the status
        $domain->status = Domain::STATUS_NEW;

        // Note: $domain->save() is invoked between 'restoring' and 'restored' events
    }

    /**
     * Handle the domain "restored" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function restored(Domain $domain)
    {
        // Create the domain in LDAP again
        \App\Jobs\Domain\CreateJob::dispatch($domain->id);
    }
}
