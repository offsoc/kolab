<?php

namespace Database\Seeds;

use App\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Discount::create(
            [
                'description' => 'Free Account',
                'discount' => 100,
                'active' => true,
            ]
        );

        Discount::create(
            [
                'description' => 'Student or Educational Institution',
                'discount' => 30,
                'active' => true,
            ]
        );

        Discount::create(
            [
                'description' => 'Test voucher',
                'discount' => 10,
                'active' => true,
                'code' => 'TEST',
            ]
        );

        // We're running in reseller mode, add a sample discount
        $tenants = \App\Tenant::where('id', '!=', \config('app.tenant_id'))->get();

        foreach ($tenants as $tenant) {
            $discount = Discount::create(
                [
                    'description' => "Sample Discount by Reseller '{$tenant->title}'",
                    'discount' => 10,
                    'active' => true,
                ]
            );

            $discount->tenant_id = $tenant->id;
            $discount->save();
        }
    }
}
