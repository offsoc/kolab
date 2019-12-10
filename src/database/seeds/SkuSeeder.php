<?php

use App\Sku;
use Illuminate\Database\Seeder;

class SkuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Sku::create(
            [
                'title' => 'mailbox',
                'description' => 'Just a mailbox',
                'cost' => 444,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Mailbox',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'domain',
                'description' => 'Somewhere to place a mailbox',
                'cost' => 100,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Domain',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'domain-registration',
                'description' => 'Register a domain with us',
                'cost' => 101,
                'period' => 'yearly',
                'handler_class' => 'App\Handlers\DomainRegistration',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => 'domain-hosting',
                'description' => 'Host a domain that is externally registered',
                'cost' => 100,
                'units_free' => 1,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\DomainHosting',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => 'domain-relay',
                'description' => 'A domain you host at home, for which we relay email',
                'cost' => 103,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\DomainRelay',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => 'storage',
                'description' => 'Some wiggle room',
                'cost' => 25,
                'units_free' => 2,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Storage',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'groupware',
                'description' => 'groupware functions',
                'cost' => 555,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Groupware',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'resource',
                'description' => 'Reservation taker',
                'cost' => 101,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Resource',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => 'shared_folder',
                'description' => 'A shared folder',
                'cost' => 89,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\SharedFolder',
                'active' => false,
            ]
        );
    }
}
