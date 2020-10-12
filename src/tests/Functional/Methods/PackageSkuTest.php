<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\PackageSku methods.
 */
class PackageSkuTest extends TestCase
{
    private $packageKolabTest;
    private $skuGroupwareTest;
    private $skuMailboxTest;
    private $skuStorageTest;

    public function setUp(): void
    {
        parent::setUp();

        $skuGroupware = \App\Sku::where('title', 'groupware')->first();
        $skuGroupwareTestAttributes = $skuGroupware->toArray();
        $skuGroupwareTestAttributes['title'] = 'groupware-test';
        $this->skuGroupwareTest = \App\Sku::create($skuGroupwareTestAttributes);

        $skuMailbox = \App\Sku::where('title', 'mailbox')->first();
        $skuMailboxTestAttributes = $skuMailbox->toArray();
        $skuMailboxTestAttributes['title'] = 'mailbox-test';
        $this->skuMailboxTest = \App\Sku::create($skuMailboxTestAttributes);

        $skuStorage = \App\Sku::where('title', 'storage')->first();
        $skuStorageTestAttributes = $skuStorage->toArray();
        $skuStorageTestAttributes['title'] = 'storage-test';
        $this->skuStorageTest = \App\Sku::create($skuStorageTestAttributes);

        $this->packageKolabTest = \App\Package::create(
            [
                'title' => 'kolab-test',
                'description' => 'A test package',
                'name' => "A Kolab Test Package"
            ]
        );

        $this->packageKolabTest->skus()->saveMany(
            [
                $this->skuGroupwareTest,
                $this->skuMailboxTest,
                $this->skuStorageTest
            ]
        );

        $this->packageKolabTest->skus()->updateExistingPivot(
            $this->skuStorageTest,
            ['qty' => 2],
            false
        );
    }

    public function tearDown(): void
    {
        $this->packageKolabTest->forceDelete();
        $this->skuGroupwareTest->forceDelete();
        $this->skuMailboxTest->forceDelete();
        $this->skuStorageTest->forceDelete();

        parent::tearDown();
    }

    public function testCostNegativeNetUnits()
    {
        foreach ($this->packageKolabTest->skus as $sku) {
            $sku->units_free = $sku->pivot->qty + 1;
            $sku->save();

            $this->assertEqual($sku->pivot->cost(), 0);

            $sku->units_free -= 1;
            $sku->save();
        }
    }

    public function testCost()
    {
        foreach ($this->packageKolabTest->skus as $sku) {
            $this->assertEqual($sku->pivot->cost(), $sku->cost * ($sku->pivot->qty - $sku->units_free));
        }
    }

    public function testCostWithPackageDiscountRate()
    {
        foreach ([15, 30, 50, 100] as $rate) {
            $this->packageKolabTest->discount_rate = $rate;
            $this->packageKolabTest->save();

            $multiplier = ((100 - $rate) / 100);

            foreach ($this->packageKolabTest->fresh()->skus as $sku) {
                $expected = $multiplier * $sku->cost * ($sku->pivot->qty - $sku->units_free);

                $this->assertEqual($sku->pivot->cost(), $expected);
            }
        }
    }

    public function testPackage()
    {
        foreach ($this->packageKolabTest->skus as $sku) {
            $this->assertSame($sku->pivot->package->id, $this->packageKolabTest->id);
        }
    }

    public function testQty()
    {
        $this->markTestIncomplete();
    }

    public function testSku()
    {
        $this->markTestIncomplete();
    }

    public function testSkuInactive()
    {
        $this->markTestIncomplete();
    }
}
