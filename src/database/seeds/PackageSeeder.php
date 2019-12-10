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
        $package = Package::create(
            [
                'title' => 'kolab',
                'description' => 'A fully functional groupware account.',
                'discount_rate' => 0
            ]
        );

        $skus = [
            Sku::firstOrCreate(['title' => 'mailbox']),
            Sku::firstOrCreate(['title' => 'storage']),
            Sku::firstOrCreate(['title' => 'groupware'])
        ];

        $package->skus()->saveMany($skus);

        // This package contains 2 units of the storage SKU, which just so happens to also
        // be the number of SKU free units.
        $package->skus()->updateExistingPivot(
            Sku::firstOrCreate(['title' => 'storage']),
            array('qty' => 2),
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
            Sku::firstOrCreate(['title' => 'mailbox']),
            Sku::firstOrCreate(['title' => 'storage'])
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
                'description' => 'Use your own domain.',
                'discount_rate' => 0
            ]
        );

        $skus = [
            Sku::firstOrCreate(['title' => 'domain-hosting'])
        ];

        $package->skus()->saveMany($skus);
    }
}
