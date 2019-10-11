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
        $this->call(DomainSeeder::class);
        $this->call(SkuSeeder::class);

        $this->call(UserSeeder::class);
    }
}
