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

        $packageDomain = \App\Package::where('title', 'domain-hosting')->first();
        $packageKolab = \App\Package::where('title', 'kolab')->first();

        $domain->assignPackage($packageDomain, $john);
        $john->assignPackage($packageKolab);

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

        $john->assignPackage($packageKolab, $jack);

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

        $john->assignPackage($packageKolab, $ned);

        // Ned is a controller on Jack's wallet
        $john->wallets()->first()->addController($ned);

        $juan = User::create(
            [
                'name' => 'Juan Despacido',
                'email' => 'juan@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $juan->setSettings(
            [
                'limit_geo' => json_encode(["CH"]),
                'guam_plz' => true,
                '2fa_plz' => true
            ]
        );

        $john->assignPackage($packageKolab, $juan);

        $piet = User::create(
            [
                'name' => 'Piet Klaassen',
                'email' => 'piet@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $piet->setSetting('limit_geo', json_encode(["NL"]));

        $john->assignPackage($packageKolab, $piet);

        factory(User::class, 10)->create();
    }
}
