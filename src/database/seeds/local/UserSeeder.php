<?php

namespace Database\Seeds\Local;

use App\Auth\SecondFactor;
use App\Domain;
use App\Entitlement;
use App\User;
use App\Sku;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use App\Wallet;

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
                'email' => 'john@kolab.org',
                'password' => 'simple123',
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
                'organization' => 'Kolab Developers',
                'phone' => '+1 509-248-1111',
            ]
        );

        $john->setAliases(['john.doe@kolab.org']);

        $wallet = $john->wallets->first();

        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_lite = \App\Package::where('title', 'lite')->first();

        $domain->assignPackage($package_domain, $john);
        $john->assignPackage($package_kolab);

        $jack = User::create(
            [
                'email' => 'jack@kolab.org',
                'password' => 'simple123',
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
            $entitlement->created_at = Carbon::now()->subMonthsWithoutOverflow(1);
            $entitlement->updated_at = Carbon::now()->subMonthsWithoutOverflow(1);
            $entitlement->save();
        }

        $ned = User::create(
            [
                'email' => 'ned@kolab.org',
                'password' => 'simple123',
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

        $ned->assignSku(\App\Sku::where('title', 'activesync')->first(), 1);

        // Ned is a controller on Jack's wallet
        $john->wallets()->first()->addController($ned);

        // Ned is also our 2FA test user
        $sku2fa = Sku::firstOrCreate(['title' => '2fa']);
        $ned->assignSku($sku2fa);
        SecondFactor::seed('ned@kolab.org');

        $joe = User::create(
            [
                'email' => 'joe@kolab.org',
                'password' => 'simple123',
            ]
        );

        $john->assignPackage($package_lite, $joe);

        $joe->setAliases(['joe.monster@kolab.org']);

        factory(User::class, 10)->create();

        $jeroen = User::create(
            [
                'email' => 'jeroen@jeroen.jeroen',
                'password' => 'jeroen',
            ]
        );

        $jeroen->role = "admin";

        $jeroen->save();
    }
}
