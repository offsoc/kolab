<?php

use App\Package;
use App\Sku;
use Illuminate\Database\Seeder;

// phpcs:ignore
class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $skuGroupware = Sku::firstOrCreate(['title' => 'groupware']);
        $skuMailbox = Sku::firstOrCreate(['title' => 'mailbox']);
        $skuStorage = Sku::firstOrCreate(['title' => 'storage']);

        $package = Package::create(
            [
                'title' => 'kolab',
                'description' => 'A fully functional groupware account.',
                'discount_rate' => 0
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
            ['qty' => 2],
            false
        );

        $package = Package::create(
            [
                'title' => 'lite',
                'description' => 'Just mail and no more.',
                'discount_rate' => 0
            ]
        );

        $skus = [
            $skuMailbox,
            $skuStorage
        ];

        $package->skus()->saveMany($skus);

        $package->skus()->updateExistingPivot(
            Sku::firstOrCreate(['title' => 'storage']),
            ['qty' => 2],
            false
        );

        $package = Package::create(
            [
                'title' => 'domain-hosting',
                'description' => 'Use your own, existing domain.',
                'discount_rate' => 0
            ]
        );

        $skus = [
            Sku::firstOrCreate(['title' => 'domain-hosting'])
        ];

        $package->skus()->saveMany($skus);
    }
}
