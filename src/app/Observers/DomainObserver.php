<?php

namespace App\Observers;

use App\Domain;
use App\EventLog;
use App\Jobs\Domain\CreateJob;
use App\Jobs\Domain\DeleteJob;
use App\Jobs\Domain\UpdateJob;
use App\Policy\RateLimit\Whitelist;

class DomainObserver
{
    /**
     * Handle the domain "created" event.
     *
     * @param Domain $domain the domain
     */
    public function creating(Domain $domain): void
    {
        $domain->namespace = \strtolower($domain->namespace);

        $domain->status |= Domain::STATUS_NEW;
    }

    /**
     * Handle the domain "created" event.
     *
     * @param Domain $domain the domain
     */
    public function created(Domain $domain)
    {
        // Create domain record in LDAP
        // Note: DomainCreate job will dispatch DomainVerify job
        CreateJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "deleted" event.
     *
     * @param Domain $domain the domain
     */
    public function deleted(Domain $domain)
    {
        if ($domain->isForceDeleting()) {
            // Remove EventLog records
            EventLog::where('object_id', $domain->id)->where('object_type', Domain::class)->delete();

            return;
        }

        DeleteJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "deleting" event.
     *
     * @param Domain $domain the domain
     */
    public function deleting(Domain $domain)
    {
        Whitelist::where(
            [
                'whitelistable_id' => $domain->id,
                'whitelistable_type' => Domain::class,
            ]
        )->delete();
    }

    /**
     * Handle the domain "updated" event.
     *
     * @param Domain $domain the domain
     */
    public function updated(Domain $domain)
    {
        if (!$domain->trashed()) {
            UpdateJob::dispatch($domain->id);
        }
    }

    /**
     * Handle the domain "restoring" event.
     *
     * @param Domain $domain the domain
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
     * @param Domain $domain the domain
     */
    public function restored(Domain $domain)
    {
        // Create the domain in LDAP again
        CreateJob::dispatch($domain->id);
    }
}
