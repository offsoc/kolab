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
     * Handle the domain "deleting" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function deleting(Domain $domain)
    {
        // Entitlements do not have referential integrity on the entitled object, so this is our
        // way of doing an onDelete('cascade') without the foreign key.
        \App\Entitlement::where('entitleable_id', $domain->id)
            ->where('entitleable_type', Domain::class)
            ->delete();
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
        // Make sure it's not DELETED/LDAP_READY/SUSPENDED
        if ($domain->isDeleted()) {
            $domain->status ^= Domain::STATUS_DELETED;
        }
        if ($domain->isLdapReady()) {
            $domain->status ^= Domain::STATUS_LDAP_READY;
        }
        if ($domain->isSuspended()) {
            $domain->status ^= Domain::STATUS_SUSPENDED;
        }
        if ($domain->isConfirmed() && $domain->isVerified()) {
            $domain->status |= Domain::STATUS_ACTIVE;
        }

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
        // Restore domain entitlements
        \App\Entitlement::restoreEntitlementsFor($domain);

        // Create the domain in LDAP again
        \App\Jobs\Domain\CreateJob::dispatch($domain->id);
    }

    /**
     * Handle the domain "force deleted" event.
     *
     * @param \App\Domain $domain The domain.
     *
     * @return void
     */
    public function forceDeleted(Domain $domain)
    {
        //
    }
}
