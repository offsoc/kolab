<?php

namespace App\Observers;

use App\Domain;

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
        // Create domain record in LDAP, then check if it exists in DNS
/*
        $chain = [
            new \App\Jobs\DomainVerify($domain),
        ];

        \App\Jobs\DomainCreate::withChain($chain)->dispatch($domain);
*/
        \App\Jobs\DomainCreate::dispatch($domain);
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
        //
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
        //
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
