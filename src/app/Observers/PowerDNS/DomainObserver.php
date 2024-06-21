<?php

namespace App\Observers\PowerDNS;

use App\PowerDNS\Domain;

class DomainObserver
{
    public function created(Domain $domain)
    {
        \App\PowerDNS\Record::create(
            [
                'domain_id' => $domain->id,
                'name' => $domain->name,
                'type' => "SOA",
                'content' => sprintf(
                    "ns.%s. hostmaster.%s. %s 1200 600 1814400 60",
                    $domain->name,
                    $domain->name,
                    \Carbon\Carbon::now()->format('Ymd') . '01'
                )
            ]
        );

        \App\PowerDNS\Record::withoutEvents(
            function () use ($domain) {
                \App\PowerDNS\Record::create(
                    [
                        'domain_id' => $domain->id,
                        'name' => $domain->name,
                        'type' => "NS",
                        'content' => \config('app.woat_ns1')
                    ]
                );
            }
        );

        \App\PowerDNS\Record::withoutEvents(
            function () use ($domain) {
                \App\PowerDNS\Record::create(
                    [
                        'domain_id' => $domain->id,
                        'name' => $domain->name,
                        'type' => "NS",
                        'content' => \config('app.woat_ns2')
                    ]
                );
            }
        );

        \App\PowerDNS\DomainSetting::create(
            [
                'domain_id' => $domain->id,
                'kind' => 'ENABLE-LUA-RECORDS',
                'content' => "0"
            ]
        );
    }
}
