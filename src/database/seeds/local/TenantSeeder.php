<?php

namespace Database\Seeds\Local;

use App\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!Tenant::find(1)) {
            Tenant::create([
                    'title' => 'Kolab Now'
            ]);
        }

        if (!Tenant::find(2)) {
            Tenant::create([
                    'title' => 'Sample Tenant'
            ]);
        }
    }
}
