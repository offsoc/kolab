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
                DomainSeeder::class,
                SkuSeeder::class,
                UserSeeder::class,
            ]
        );
    }
}
