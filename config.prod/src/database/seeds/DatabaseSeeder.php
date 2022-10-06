<?php

use Illuminate\Database\Seeder;
use Database\Seeds;

// phpcs:ignore
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            Seeds\AppKeySeeder::class,
            Seeds\PassportSeeder::class,
            Seeds\PowerDNSSeeder::class,
            Seeds\SkuSeeder::class,
            Seeds\AdminSeeder::class,
        ]);
    }
}
