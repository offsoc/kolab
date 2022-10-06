<?php

namespace Database\Seeds;

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
        if (\config('app.tenant_id')) {
            $tenant = Tenant::where(['title' => 'Kolab Now'])->first();

            if (!$tenant) {
                Tenant::create(['title' => 'Kolab Now']);
            }

            $tenant = Tenant::where(['title' => 'Sample Tenant'])->first();

            if (!$tenant) {
                $tenant = Tenant::create(['title' => 'Sample Tenant']);
            }

            $tenant = Tenant::where(['title' => 'kanarip.ch'])->first();

            if (!$tenant) {
                $tenant = Tenant::create(['title' => 'kanarip.ch']);
            }
        }
    }
}
