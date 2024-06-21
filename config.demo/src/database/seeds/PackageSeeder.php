<?php

namespace Database\Seeds;

use App\Package;
use App\Sku;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $skuDomain = Sku::where(['title' => 'domain-hosting', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuGroupware = Sku::where(['title' => 'groupware', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuMailbox = Sku::where(['title' => 'mailbox', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuStorage = Sku::where(['title' => 'storage', 'tenant_id' => \config('app.tenant_id')])->first();

        $package = Package::create(
            [
                'title' => 'kolab',
                'name' => 'Groupware Account',
                'description' => 'A fully functional groupware account.',
                'discount_rate' => 0,
            ]
        );

        $skus = [
            $skuMailbox,
            $skuGroupware,
            $skuStorage
        ];

        $package->skus()->saveMany($skus);

        // This package contains 2 units of the storage SKU, which just so happens to also
        // be the number of SKU free units.
        $package->skus()->updateExistingPivot(
            $skuStorage,
            ['qty' => 5],
            false
        );

        $package = Package::create(
            [
                'title' => 'lite',
                'name' => 'Lite Account',
                'description' => 'Just mail and no more.',
                'discount_rate' => 0,
            ]
        );

        $skus = [
            $skuMailbox,
            $skuStorage
        ];

        $package->skus()->saveMany($skus);

        $package->skus()->updateExistingPivot(
            $skuStorage,
            ['qty' => 5],
            false
        );

        $package = Package::create(
            [
                'title' => 'domain-hosting',
                'name' => 'Domain Hosting',
                'description' => 'Use your own, existing domain.',
                'discount_rate' => 0,
            ]
        );

        $skus = [
            $skuDomain
        ];

        $package->skus()->saveMany($skus);

        // We're running in reseller mode, add a sample discount
        $tenants = \App\Tenant::where('id', '!=', \config('app.tenant_id'))->get();

        foreach ($tenants as $tenant) {
            $skuDomain = Sku::where(['title' => 'domain-hosting', 'tenant_id' => $tenant->id])->first();
            $skuGroupware = Sku::where(['title' => 'groupware', 'tenant_id' => $tenant->id])->first();
            $skuMailbox = Sku::where(['title' => 'mailbox', 'tenant_id' => $tenant->id])->first();
            $skuStorage = Sku::where(['title' => 'storage', 'tenant_id' => $tenant->id])->first();

            $package = Package::create(
                [
                    'title' => 'kolab',
                    'name' => 'Groupware Account',
                    'description' => 'A fully functional groupware account.',
                    'discount_rate' => 0
                ]
            );

            $package->tenant_id = $tenant->id;
            $package->save();

            $skus = [
                $skuMailbox,
                $skuGroupware,
                $skuStorage
            ];

            $package->skus()->saveMany($skus);

            // This package contains 2 units of the storage SKU, which just so happens to also
            // be the number of SKU free units.
            $package->skus()->updateExistingPivot(
                $skuStorage,
                ['qty' => 5],
                false
            );

            $package = Package::create(
                [
                    'title' => 'lite',
                    'name' => 'Lite Account',
                    'description' => 'Just mail and no more.',
                    'discount_rate' => 0
                ]
            );

            $package->tenant_id = $tenant->id;
            $package->save();

            $skus = [
                $skuMailbox,
                $skuStorage
            ];

            $package->skus()->saveMany($skus);

            $package->skus()->updateExistingPivot(
                $skuStorage,
                ['qty' => 5],
                false
            );

            $package = Package::create(
                [
                    'title' => 'domain-hosting',
                    'name' => 'Domain Hosting',
                    'description' => 'Use your own, existing domain.',
                    'discount_rate' => 0
                ]
            );

            $package->tenant_id = $tenant->id;
            $package->save();

            $skus = [
                $skuDomain
            ];

            $package->skus()->saveMany($skus);
        }
    }
}
