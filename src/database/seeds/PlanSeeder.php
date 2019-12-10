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
    }
}
