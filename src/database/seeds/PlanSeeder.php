<?php

use App\Package;
use App\Plan;
use Illuminate\Database\Seeder;

// phpcs:ignore
class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
        $plan = Plan::create(
            [
                'title' => 'family',
                'description' => 'A group of accounts for 2 or more users.',
                'discount_qty' => 0,
                'discount_rate' => 0
            ]
        );

        $packages = [
            Package::firstOrCreate(['title' => 'kolab']),
            Package::firstOrCreate(['title' => 'domain-hosting'])
        ];

        $plan->packages()->saveMany($packages);

        $plan->packages()->updateExistingPivot(
            Package::firstOrCreate(['title' => 'kolab']),
            [
                'qty_min' => 2,
                'qty_max' => -1,
                'discount_qty' => 2,
                'discount_rate' => 50
            ],
            false
        );

        $plan = Plan::create(
            [
                'title' => 'small-business',
                'description' => 'Accounts for small business owners.',
                'discount_qty' => 0,
                'discount_rate' => 10
            ]
        );

        $packages = [
            Package::firstOrCreate(['title' => 'kolab']),
            Package::firstOrCreate(['title' => 'domain-hosting'])
        ];

        $plan->packages()->saveMany($packages);

        $plan->packages()->updateExistingPivot(
            Package::firstOrCreate(['title' => 'kolab']),
            [
                'qty_min' => 5,
                'qty_max' => 25,
                'discount_qty' => 5,
                'discount_rate' => 30
            ],
            false
        );

        $plan = Plan::create(
            [
                'title' => 'large-business',
                'description' => 'Accounts for large businesses.',
                'discount_qty' => 0,
                'discount_rate' => 10
            ]
        );

        $packages = [
            Package::firstOrCreate(['title' => 'kolab']),
            Package::firstOrCreate(['title' => 'lite']),
            Package::firstOrCreate(['title' => 'domain-hosting'])
        ];

        $plan->packages()->saveMany($packages);

        $plan->packages()->updateExistingPivot(
            Package::firstOrCreate(['title' => 'kolab']),
            [
                'qty_min' => 20,
                'qty_max' => -1,
                'discount_qty' => 10,
                'discount_rate' => 10
            ],
            false
        );

        $plan->packages()->updateExistingPivot(
            Package::firstOrCreate(['title' => 'lite']),
            [
                'qty_min' => 0,
                'qty_max' => -1,
                'discount_qty' => 10,
                'discount_rate' => 10
            ],
            false
        );
        */

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
                'discount_qty' => 0,
                'discount_rate' => 0
            ]
        );

        $packages = [
            Package::firstOrCreate(['title' => 'kolab'])
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
                'discount_qty' => 0,
                'discount_rate' => 0
            ]
        );

        $packages = [
            Package::firstOrCreate(['title' => 'kolab']),
            Package::firstOrCreate(['title' => 'domain-hosting']),
        ];

        $plan->packages()->saveMany($packages);
    }
}
