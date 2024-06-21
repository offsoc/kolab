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
        if ($tenantId = \config('app.tenant_id')) {
            $tenant = Tenant::where(['id' => $tenantId])->first();
            if (!$tenant) {
                Tenant::create(['title' => 'Kolab Now', 'id' => $tenantId]);
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
