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
    }
}
