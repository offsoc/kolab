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
            Seeds\IP4NetSeeder::class,
            Seeds\TenantSeeder::class,
            Seeds\DiscountSeeder::class,
            Seeds\DomainSeeder::class,
            Seeds\SkuSeeder::class,
            Seeds\PackageSeeder::class,
            Seeds\PlanSeeder::class,
            Seeds\PowerDNSSeeder::class,
            Seeds\UserSeeder::class,
            Seeds\OauthClientSeeder::class,
            Seeds\ResourceSeeder::class,
            Seeds\SharedFolderSeeder::class,
            Seeds\MeetRoomSeeder::class,
        ]);
    }
}
