<?php

namespace Database\Seeds\Local;

use App\Package;
use App\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $description = <<<'EOD'
<p>Everything you need to get started or try Kolab Now, including:</p>
<ul>
    <li>Perfect for anyone wanting to move to Kolab Now</li>
    <li>Suite of online apps: Secure email, calendar, address book, files and more</li>
    <li>Access for anywhere: Sync all your devices to your Kolab Now account</li>
    <li>Secure hosting: Managed right here on our own servers in Switzerland </li>
    <li>Start protecting your data today, no ads, no crawling, no compromise</li>
    <li>An ideal replacement for services like Gmail, Office 365, etc…</li>
</ul>
EOD;

        $plan = Plan::create(
            [
                'title' => 'individual',
                'name' => 'Individual Account',
                'description' => $description,
                'free_months' => 1,
                'discount_qty' => 0,
                'discount_rate' => 0
            ]
        );

        $packages = [
            Package::where(['title' => 'kolab', 'tenant_id' => \config('app.tenant_id')])->first()
        ];

        $plan->packages()->saveMany($packages);

        $description = <<<'EOD'
<p>All the features of the Individual Account, with the following extras:</p>
<ul>
    <li>Perfect for anyone wanting to move a group or small business to Kolab Now</li>
    <li>Recommended to support users from 1 to 100</li>
    <li>Use your own personal domains with Kolab Now</li>
    <li>Manage and add users through our online admin area</li>
    <li>Flexible pricing based on user count</li>
</ul>
EOD;

        $plan = Plan::create(
            [
                'title' => 'group',
                'name' => 'Group Account',
                'description' => $description,
                'free_months' => 1,
                'discount_qty' => 0,
                'discount_rate' => 0
            ]
        );

        $packages = [
            Package::where(['title' => 'domain-hosting', 'tenant_id' => \config('app.tenant_id')])->first(),
            Package::where(['title' => 'kolab', 'tenant_id' => \config('app.tenant_id')])->first()
        ];

        $plan->packages()->saveMany($packages);

        // We're running in reseller mode, add a sample discount
        $tenants = \App\Tenant::where('id', '!=', \config('app.tenant_id'))->get();

        foreach ($tenants as $tenant) {
            $description = <<<'EOD'
<p>Everything you need to get started or try Kolab Now, including:</p>
<ul>
    <li>Perfect for anyone wanting to move to Kolab Now</li>
    <li>Suite of online apps: Secure email, calendar, address book, files and more</li>
    <li>Access for anywhere: Sync all your devices to your Kolab Now account</li>
    <li>Secure hosting: Managed right here on our own servers in Switzerland </li>
    <li>Start protecting your data today, no ads, no crawling, no compromise</li>
    <li>An ideal replacement for services like Gmail, Office 365, etc…</li>
</ul>
EOD;

            $plan = Plan::create(
                [
                    'title' => 'individual',
                    'name' => 'Individual Account',
                    'free_months' => 1,
                    'description' => $description,
                    'discount_qty' => 0,
                    'discount_rate' => 0
                ]
            );

            $plan->tenant_id = $tenant->id;
            $plan->save();

            $packages = [
                Package::where(['title' => 'kolab', 'tenant_id' => $tenant->id])->first()
            ];

            $plan->packages()->saveMany($packages);

            $description = <<<'EOD'
<p>All the features of the Individual Account, with the following extras:</p>
<ul>
    <li>Perfect for anyone wanting to move a group or small business to Kolab Now</li>
    <li>Recommended to support users from 1 to 100</li>
    <li>Use your own personal domains with Kolab Now</li>
    <li>Manage and add users through our online admin area</li>
    <li>Flexible pricing based on user count</li>
</ul>
EOD;

            $plan = Plan::create(
                [
                    'title' => 'group',
                    'name' => 'Group Account',
                    'description' => $description,
                    'free_months' => 1,
                    'discount_qty' => 0,
                    'discount_rate' => 0
                ]
            );

            $plan->tenant_id = $tenant->id;
            $plan->save();

            $packages = [
                Package::where(['title' => 'domain-hosting', 'tenant_id' => $tenant->id])->first(),
                Package::where(['title' => 'kolab', 'tenant_id' => $tenant->id])->first()
            ];

            $plan->packages()->saveMany($packages);
        }
    }
}
