<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(
            [
                DiscountSeeder::class,
                DomainSeeder::class,
                SkuSeeder::class,
                PackageSeeder::class,
                PlanSeeder::class,
                UserSeeder::class
            ]
        );
    }
}
