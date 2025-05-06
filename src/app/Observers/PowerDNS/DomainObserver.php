<?php

namespace App\Observers\PowerDNS;

use App\PowerDNS\Domain;
use App\PowerDNS\DomainSetting;
use App\PowerDNS\Record;
use Carbon\Carbon;

class DomainObserver
{
    public function created(Domain $domain)
    {
        Record::create(
            [
                'domain_id' => $domain->id,
                'name' => $domain->name,
                'type' => "SOA",
                'content' => sprintf(
                    "ns.%s. hostmaster.%s. %s 1200 600 1814400 60",
                    $domain->name,
                    $domain->name,
                    Carbon::now()->format('Ymd') . '01'
                ),
            ]
        );

        Record::withoutEvents(
            static function () use ($domain) {
                Record::create(
                    [
                        'domain_id' => $domain->id,
                        'name' => $domain->name,
                        'type' => "NS",
                        'content' => \config('app.woat_ns1'),
                    ]
                );
            }
        );

        Record::withoutEvents(
            static function () use ($domain) {
                Record::create(
                    [
                        'domain_id' => $domain->id,
                        'name' => $domain->name,
                        'type' => "NS",
                        'content' => \config('app.woat_ns2'),
                    ]
                );
            }
        );

        DomainSetting::create(
            [
                'domain_id' => $domain->id,
                'kind' => 'ENABLE-LUA-RECORDS',
                'content' => "0",
            ]
        );
    }
}
