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
                'name' => 'User Mailbox',
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
                'name' => 'Hosted Domain',
                'description' => 'Somewhere to place a mailbox',
                'cost' => 100,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Domain',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => 'domain-registration',
                'name' => 'Domain Registration',
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
                'name' => 'External Domain',
                'description' => 'Host a domain that is externally registered',
                'cost' => 100,
                'units_free' => 1,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\DomainHosting',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'domain-relay',
                'name' => 'Domain Relay',
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
                'name' => 'Storage Quota',
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
                'name' => 'Groupware Features',
                'description' => 'Groupware functions like Calendar, Tasks, Notes, etc.',
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
                'name' => 'Resource',
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
                'name' => 'Shared Folder',
                'description' => 'A shared folder',
                'cost' => 89,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\SharedFolder',
                'active' => false,
            ]
        );

        Sku::create(
            [
                'title' => '2fa',
                'name' => '2-Factor Authentication',
                'description' => 'Two factor authentication for webmail and administration panel',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Auth2F',
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'activesync',
                'name' => 'Activesync',
                'description' => 'Mobile synchronization',
                'cost' => 100,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Activesync',
                'active' => true,
            ]
        );
    }
}
