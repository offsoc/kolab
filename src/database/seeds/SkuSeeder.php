<?php

use App\Sku;
use Illuminate\Database\Seeder;

class SkuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Sku::create(
            [
                'title' => 'individual',
                'description' => 'No friends',
                'cost' => 1.00
            ]
        );

        Sku::create(
            [
                'title' => 'group',
                'description' => 'Some or many friends',
                'cost' => 1.00
            ]
        );
    }
}
