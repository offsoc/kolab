<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Package methods.
 */
class PackageTest extends TestCase
{
    private $packageDomainHosting;
    private $packageKolab;
    private $packageLite;

    private $skuDomainHosting;
    private $skuGroupware;
    private $skuMailbox;
    private $skuStorage;

    public function setUp(): void
    {
        parent::setUp();

        $this->packageDomainHosting = \App\Package::where('title', 'domain-hosting')->first();
        $this->packageKolab = \App\Package::where('title', 'kolab')->first();
        $this->packageLite = \App\Package::where('title', 'lite')->first();

        $this->skuDomainHosting = \App\Sku::where('title', 'domain-hosting')->first();
        $this->skuGroupware = \App\Sku::where('title', 'groupware')->first();
        $this->skuMailbox = \App\Sku::where('title', 'mailbox')->first();
        $this->skuStorage = \App\Sku::where('title', 'storage')->first();
    }

    public function testSkusDomainHosting()
    {
        // this is without a package quantity manipulator, just unique SKUs
        $this->assertCount(1, $this->packageDomainHosting->skus);
        $this->assertTrue($this->packageDomainHosting->skus->contains($this->skuDomainHosting));
        $this->assertFalse($this->packageDomainHosting->skus->contains($this->skuGroupware));
        $this->assertFalse($this->packageDomainHosting->skus->contains($this->skuMailbox));
        $this->assertFalse($this->packageDomainHosting->skus->contains($this->skuStorage));
    }

    public function testSkusKolab()
    {
        // this is without a package quantity manipulator, just unique SKUs
        $this->assertCount(3, $this->packageKolab->skus);
        $this->assertFalse($this->packageKolab->skus->contains($this->skuDomainHosting));
        $this->assertTrue($this->packageKolab->skus->contains($this->skuGroupware));
        $this->assertTrue($this->packageKolab->skus->contains($this->skuMailbox));
        $this->assertTrue($this->packageKolab->skus->contains($this->skuStorage));
    }

    public function testSkusLite()
    {
        // this is without a package quantity manipulator, just unique SKUs
        $this->assertCount(2, $this->packageLite->skus);
        $this->assertFalse($this->packageLite->skus->contains($this->skuDomainHosting));
        $this->assertFalse($this->packageLite->skus->contains($this->skuGroupware));
        $this->assertTrue($this->packageLite->skus->contains($this->skuMailbox));
        $this->assertTrue($this->packageLite->skus->contains($this->skuStorage));
    }

    public function testPackageIsDomainFailure()
    {
        $this->assertFalse($this->packageKolab->isDomain());
        $this->assertFalse($this->packageLite->isDomain());
    }

    public function testPackageIsDomainSuccess()
    {
        $this->assertTrue($this->packageDomainHosting->isDomain());
    }

    public function testPackageDomainHostingCost()
    {
        $this->assertEqual($this->packageDomainHosting->cost(), 0);
    }

    public function testPackageKolabCost()
    {
        $this->assertEqual($this->packageKolab->cost(), 999);
    }

    public function testPackageLiteCost()
    {
        $this->assertEqual($this->packageLite->cost(), 444);
    }

    public function testPackageCostWithNegativeNetUnits()
    {
        $skus = $this->packageLite->skus;

        foreach ($skus as $sku) {
            if ($sku->title == "storage") {
                $sku->units_free = $sku->pivot->qty + 1;
            }
        }

        $this->assertEqual($this->packageLite->cost(), 444);
    }

    public function testPackageCostWithDiscountRate()
    {
        $this->markTestIncomplete();
    }
}
