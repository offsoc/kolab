<?php

namespace Database\Seeds\Local;

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
    }
}
