<?php

namespace Tests\Feature;

use App\Entitlement;
use App\Package;
use App\PackageSku;
use App\Sku;
use Tests\TestCase;

class PackageTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Package::where('title', 'test-package')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Package::where('title', 'test-package')->delete();

        parent::tearDown();
    }

    /**
     * Test for a package's cost.
     */
    public function testCost(): void
    {
        $skuGroupware = Sku::withEnvTenantContext()->where('title', 'groupware')->first();  // cost: 490
        $skuMailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();      // cost: 500
        $skuStorage = Sku::withEnvTenantContext()->where('title', 'storage')->first();      // cost: 25

        $package = Package::create([
                'title' => 'test-package',
                'name' => 'Test Account',
                'description' => 'Test account.',
                'discount_rate' => 0,
        ]);

        // WARNING: saveMany() sets package_skus.cost = skus.cost, the next line will reset it to NULL
        $package->skus()->saveMany([
                $skuMailbox,
                $skuGroupware,
                $skuStorage
        ]);

        PackageSku::where('package_id', $package->id)->update(['cost' => null]);

        // Test a package w/o any extra parameters
        $this->assertSame(490 + 500, $package->cost());

        // Test a package with pivot's qty
        $package->skus()->updateExistingPivot(
            $skuStorage,
            ['qty' => 6],
            false
        );
        $package->refresh();

        $this->assertSame(490 + 500 + 25, $package->cost());

        // Test a package with pivot's cost
        $package->skus()->updateExistingPivot(
            $skuStorage,
            ['cost' => 100],
            false
        );
        $package->refresh();

        $this->assertSame(490 + 500 + 100, $package->cost());

        // Test a package with discount_rate
        $package->discount_rate = 30;
        $package->save();
        $package->skus()->updateExistingPivot(
            $skuMailbox,
            ['qty' => 2],
            false
        );
        $package->refresh();

        $this->assertSame((int) (round(490 * 0.7) + 2 * round(500 * 0.7) + 100), $package->cost());
    }
}
