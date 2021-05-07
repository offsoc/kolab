<?php

namespace Database\Seeds\Production;

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
                'cost' => 50,
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

        // Check existence because migration might have added this already
        if (!\App\Sku::where('title', 'beta')->first()) {
            Sku::create(
                [
                    'title' => 'beta',
                    'name' => 'Private Beta (invitation only)',
                    'description' => 'Access to the private beta program subscriptions',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Beta',
                    'active' => false,
                ]
            );
        }

        // Check existence because migration might have added this already
        if (!\App\Sku::where('title', 'meet')->first()) {
            Sku::create(
                [
                    'title' => 'meet',
                    'name' => 'Voice & Video Conferencing (public beta)',
                    'description' => 'Video conferencing tool',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Meet',
                    'active' => true,
                ]
            );
        }

        // Check existence because migration might have added this already
        if (!\App\Sku::where('title', 'group')->first()) {
            Sku::create(
                [
                    'title' => 'group',
                    'name' => 'Group',
                    'description' => 'Distribution list',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Group',
                    'active' => true,
                ]
            );
        }

        // Check existence because migration might have added this already
        if (!\App\Sku::where('title', 'distlist')->first()) {
            \App\Sku::create([
                'title' => 'distlist',
                'name' => 'Distribution lists',
                'description' => 'Access to mail distribution lists',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Distlist',
                'active' => true,
            ]);
        }
    }
}
