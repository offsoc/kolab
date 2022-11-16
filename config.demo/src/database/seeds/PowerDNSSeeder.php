<?php

namespace Database\Seeds;

use App\Domain;
use Illuminate\Database\Seeder;

class PowerDNSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $domain = \App\PowerDNS\Domain::create(
            [
                'name' => '_woat.' . \config('app.domain')
            ]
        );

        /*
        $domainLuaSetting = \App\PowerDNS\DomainSetting::where(
            ['domain_id' => $domain->id, 'kind' => 'ENABLE-LUA-RECORDS']
        )->first();

        $domainLuaSetting->{'content'} = "1";
        $domainLuaSetting->save();
        */

        /*
        \App\PowerDNS\Record::create(
            [
                'domain_id' => $domain->id,
                'name' => $domain->{'name'},
                'type' => 'A',
                'content' => '10.4.2.23'
            ]
        );
        */
    }
}
