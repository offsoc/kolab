<?php

use Illuminate\Database\Seeder;

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
        // Define seeders order
        $seeders = [
            'TenantSeeder',
            'DiscountSeeder',
            'DomainSeeder',
            'SkuSeeder',
            'PackageSeeder',
            'PlanSeeder',
            'UserSeeder',
            'OpenViduRoomSeeder',
        ];

        $env = ucfirst(App::environment());

        // Check if the seeders exists
        foreach ($seeders as $idx => $name) {
            $class = "Database\\Seeds\\$env\\$name";
            $seeders[$idx] = class_exists($class) ? $class : null;
        }

        $seeders = array_filter($seeders);

        $this->call($seeders);
    }
}
