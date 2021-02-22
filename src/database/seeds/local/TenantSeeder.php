<?php

namespace Database\Seeds\Local;

use App\Tenant;
use Illuminate\Database\Seeder;

// phpcs:ignore
class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Tenant::create(
            [
                'title' => 'Kolab Now'
            ]
        );

        Tenant::create(
            [
                'title' => 'Sample Tenant'
            ]
        );
    }
}
