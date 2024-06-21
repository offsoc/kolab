<?php

namespace Database\Seeds;

use App\IP4Net;
use Illuminate\Database\Seeder;

class IP4NetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // necessary for tests
        IP4Net::create(
            [
                'rir_name' => 'ripencc',
                'net_number' => '212.103.64.0',
                'net_mask' => 19,
                'net_broadcast' => '212.103.95.255',
                'country' => 'CH',
                'serial' => 1,
                'created_at' => '1999-02-05 00:00:00',
                'updated_at' => '2021-07-30 08:49:30'
            ]
        );
    }
}
