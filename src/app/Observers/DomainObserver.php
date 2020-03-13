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
    public function creating(Domain $domain)
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidInt();
            if (!Domain::find($allegedly_unique)) {
                $domain->{$domain->getKeyName()} = $allegedly_unique;
                break;
            }
        }

        $domain->status |= Domain::STATUS_NEW | Domain::STATUS_ACTIVE;
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
        // Create domain record in LDAP, then check if it exists in DNS
        \App\Jobs\DomainCreate::dispatch($domain);
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
        \App\Jobs\DomainDelete::dispatch($domain->id);
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
        \App\Jobs\DomainUpdate::dispatch($domain->id);
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
        //
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
