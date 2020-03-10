<?php

use App\Domain;
use App\Entitlement;
use App\User;
use App\Sku;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use App\Wallet;

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
                'status' => Domain::STATUS_NEW
                    + Domain::STATUS_ACTIVE
                    + Domain::STATUS_CONFIRMED
                    + Domain::STATUS_VERIFIED,
                'type' => Domain::TYPE_EXTERNAL
            ]
        );

        $john = User::create(
            [
                'name' => 'John Doe',
                'email' => 'john@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $john->setSettings(
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'currency' => 'USD',
                'country' => 'US',
                'billing_address' => "601 13th Street NW\nSuite 900 South\nWashington, DC 20005",
                'external_email' => 'john.doe.external@gmail.com',
                'phone' => '+1 509-248-1111',
            ]
        );

        $john->setAliases(['john.doe@kolab.org']);

        $wallet = $john->wallets->first();

        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $package_kolab = \App\Package::where('title', 'kolab')->first();

        $domain->assignPackage($package_domain, $john);
        $john->assignPackage($package_kolab);

        $jack = User::create(
            [
                'name' => 'Jack Daniels',
                'email' => 'jack@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $jack->setSettings(
            [
                'first_name' => 'Jack',
                'last_name' => 'Daniels',
                'currency' => 'USD',
                'country' => 'US'
            ]
        );

        $jack->setAliases(['jack.daniels@kolab.org']);

        $john->assignPackage($package_kolab, $jack);

        foreach ($john->entitlements as $entitlement) {
            $entitlement->created_at = Carbon::now()->subMonths(1);
            $entitlement->updated_at = Carbon::now()->subMonths(1);
            $entitlement->save();
        }

        $ned = User::create(
            [
                'name' => 'Edward Flanders',
                'email' => 'ned@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $ned->setSettings(
            [
                'first_name' => 'Edward',
                'last_name' => 'Flanders',
                'currency' => 'USD',
                'country' => 'US'
            ]
        );

        $john->assignPackage($package_kolab, $ned);

        // Ned is a controller on Jack's wallet
        $john->wallets()->first()->addController($ned);

        factory(User::class, 10)->create();
    }
}
