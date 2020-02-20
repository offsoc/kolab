<?php

use Illuminate\Database\Seeder;
use App\Domain;
use App\Entitlement;
use App\User;
use App\Sku;

// phpcs:ignore
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $domain = Domain::create(
            [
                'namespace' => 'kolab.org',
                'status' => Domain::STATUS_NEW + Domain::STATUS_ACTIVE + Domain::STATUS_CONFIRMED + Domain::STATUS_VERIFIED,
                'type' => Domain::TYPE_EXTERNAL
            ]
        );

        $john = User::create(
            [
                'name' => "John Doe",
                'email' => 'john@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $john->setSettings(
            [
                "first_name" => "John",
                "last_name" => "Doe",
                "currency" => "USD",
                "country" => "US"
            ]
        );

        $user_wallets = $john->wallets()->get();

        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $package_kolab = \App\Package::where('title', 'kolab')->first();

        $domain->assignPackage($package_domain, $john);
        $john->assignPackage($package_kolab);

        $jack = User::create(
            [
                'name' => "Jack Daniels",
                'email' => 'jack@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $jack->setSettings(
            [
                "first_name" => "Jack",
                "last_name" => "Daniels",
                "currency" => "USD",
                "country" => "US"
            ]
        );

        $john->assignPackage($package_kolab, $jack);

        factory(User::class, 10)->create();
    }
}
