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
                'status' => Domain::STATUS_NEW + Domain::STATUS_ACTIVE + Domain::STATUS_CONFIRMED,
                'type' => Domain::TYPE_EXTERNAL
            ]
        );

        $user = User::create(
            [
                'name' => "John Doe",
                'email' => 'john@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $user_wallets = $user->wallets()->get();

        $sku_domain = Sku::where('title', 'domain')->first();
        $sku_mailbox = Sku::where('title', 'mailbox')->first();

        $entitlement_domain = Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $user_wallets[0]->id,
                'sku_id' => $sku_domain->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
            ]
        );

        $entitlement_mailbox = Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $user_wallets[0]->id,
                'sku_id' => $sku_mailbox->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class

        $user->setSettings(
            [
                "first_name" => "John",
                "last_name" => "Doe",
                "currency" => "USD",
                "country" => "US"
            ]
        );

        // 10'000 users result in a table size of 11M
        //foreach (range(1, 1000) as $number) {
        //    factory(User::class, 1000)->create();
        //}
        factory(User::class, 100)->create();
        //factory(User::class, 3)->create();

        $uids = [
            'adomaitis' => [
                "first_name" => "Liutauras",
                "last_name" => "Adomaitis",
                "currency" => "EUR",
                "country" => "LT",
            ],
            'bohlender' => [
                "first_name" => "Michael",
                "last_name" => "Bohlender",
                "currency" => "EUR",
                "country" => "DE",
            ],
            'leickel' => [
                "first_name" => "Lioba",
                "last_name" => "Leickel",
                "currency" => "EUR",
                "country" => "DE",
            ],
            'machniak' => [
                "first_name" => "Aleksander",
                "last_name" => "Machniak",
                "currency" => "EUR",
                "country" => "PL",
            ],
            'mollekopf' => [
                "first_name" => "Christian",
                "last_name" => "Mollekopf",
            ],
            'petersen' => [
                "first_name" => "Mads",
                "last_name" => "Petersen",
            ],
            'vanmeeuwen' => [
                "first_name" => "Jeroen",
                "last_name" => "van Meeuwen",
            ],
            'winniewski' => [
                "first_name" => "Nanita",
                "last_name" => "Winniewski",
                "currency" => "EUR",
                "country" => "DE"
            ]
        ];

        foreach ($uids as $uid => $settings) {
            $user = User::create(
                [
                    'name' => $uid,
                    'email' => "{$uid}@kolabsystems.com",
                    'password' => 'simple123',
                    'email_verified_at' => now()
                ]
            );

            $user->setSettings($settings);
        }
    }
}
