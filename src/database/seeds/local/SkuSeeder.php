<?php

namespace Database\Seeds\Local;

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
                'cost' => 500,
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
                'units_free' => 5,
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
                'cost' => 490,
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
                'active' => true,
            ]
        );

        Sku::create(
            [
                'title' => 'shared-folder',
                'name' => 'Shared Folder',
                'description' => 'A shared folder',
                'cost' => 89,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\SharedFolder',
                'active' => true,
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
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Activesync',
                'active' => true,
            ]
        );

        // Check existence because migration might have added this already
        $sku = Sku::where(['title' => 'beta', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
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
        $sku = Sku::where(['title' => 'meet', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
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
        $sku = Sku::where(['title' => 'group', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
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
        $sku = Sku::where(['title' => 'beta-distlists', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
            Sku::create(
                [
                    'title' => 'beta-distlists',
                    'name' => 'Distribution lists',
                    'description' => 'Access to mail distribution lists',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Beta\Distlists',
                    'active' => true,
                ]
            );
        }

        // Check existence because migration might have added this already
        $sku = Sku::where(['title' => 'beta-resources', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
            Sku::create([
                'title' => 'beta-resources',
                'name' => 'Calendaring resources',
                'description' => 'Access to calendaring resources',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\Resources',
                'active' => true,
            ]);
        }

        // Check existence because migration might have added this already
        $sku = Sku::where(['title' => 'beta-shared-folders', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
            Sku::create([
                'title' => 'beta-shared-folders',
                'name' => 'Shared folders',
                'description' => 'Access to shared folders',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\SharedFolders',
                'active' => true,
            ]);
        }

        // Check existence because migration might have added this already
        $sku = Sku::where(['title' => 'files', 'tenant_id' => \config('app.tenant_id')])->first();

        if (!$sku) {
            Sku::create([
                'title' => 'files',
                'name' => 'File storage',
                'description' => 'Access to file storage',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Files',
                'active' => true,
            ]);
        }

        // for tenants that are not the configured tenant id
        $tenants = \App\Tenant::where('id', '!=', \config('app.tenant_id'))->get();

        foreach ($tenants as $tenant) {
            $sku = Sku::create(
                [
                    'title' => 'mailbox',
                    'name' => 'User Mailbox',
                    'description' => 'Just a mailbox',
                    'cost' => 500,
                    'fee' => 333,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Mailbox',
                    'active' => true,
                ]
            );

            $sku->tenant_id = $tenant->id;
            $sku->save();

            $sku = Sku::create(
                [
                    'title' => 'storage',
                    'name' => 'Storage Quota',
                    'description' => 'Some wiggle room',
                    'cost' => 25,
                    'fee' => 16,
                    'units_free' => 5,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Storage',
                    'active' => true,
                ]
            );

            $sku->tenant_id = $tenant->id;
            $sku->save();

            $sku = Sku::create(
                [
                    'title' => 'domain-hosting',
                    'name' => 'External Domain',
                    'description' => 'Host a domain that is externally registered',
                    'cost' => 100,
                    'fee' => 66,
                    'units_free' => 1,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\DomainHosting',
                    'active' => true,
                ]
            );

            $sku->tenant_id = $tenant->id;
            $sku->save();

            $sku = Sku::create(
                [
                    'title' => 'groupware',
                    'name' => 'Groupware Features',
                    'description' => 'Groupware functions like Calendar, Tasks, Notes, etc.',
                    'cost' => 490,
                    'fee' => 327,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Groupware',
                    'active' => true,
                ]
            );

            $sku->tenant_id = $tenant->id;
            $sku->save();

            $sku = Sku::create(
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

            $sku->tenant_id = $tenant->id;
            $sku->save();

            $sku = Sku::create(
                [
                    'title' => 'activesync',
                    'name' => 'Activesync',
                    'description' => 'Mobile synchronization',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Activesync',
                    'active' => true,
                ]
            );

            $sku->tenant_id = $tenant->id;
            $sku->save();
        }
    }
}
