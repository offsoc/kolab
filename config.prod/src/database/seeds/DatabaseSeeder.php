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
            Seeds\PassportSeeder::class,
            Seeds\PowerDNSSeeder::class,
            Seeds\AdminSeeder::class,
            Seeds\ImapAdminSeeder::class,
            Seeds\NoreplySeeder::class,
        ]);
    }
}
