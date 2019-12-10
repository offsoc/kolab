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
                'discount' => 0
            ]
        );

        $skus = [
            Sku::firstOrCreate(['title' => 'mailbox']),
            Sku::firstOrCreate(['title' => 'storage']),
            Sku::firstOrCreate(['title' => 'groupware'])
        ];

        $package->skus()->saveMany($skus);

        $package->skus()->updateExistingPivot(Sku::firstOrCreate(['title' => 'storage']), array('qty' => 2), false);

        $package = Package::create(
            [
                'title' => 'lite',
                'description' => 'Just mail and no more.',
                'discount' => 0
            ]
        );

        $skus = [
            Sku::firstOrCreate(['title' => 'mailbox']),
            Sku::firstOrCreate(['title' => 'storage'])
        ];

        $package->skus()->saveMany($skus);

        $package->skus()->updateExistingPivot(Sku::firstOrCreate(['title' => 'storage']), array('qty' => 2), false);
    }
}
