<?php

namespace Tests\Functional\Methods;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify the functioning of \App\Discount methods where additional infrastructure is required, such as Redis and
 * MariaDB.
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
    }

    public function testSkusKolab()
    {
        // this is without a package quantity manipulator, just unique SKUs
        $this->assertCount(3, $this->packageKolab->skus);
        $this->assertTrue($this->packageKolab->skus->contains($this->skuGroupware));
        $this->assertTrue($this->packageKolab->skus->contains($this->skuMailbox));
        $this->assertTrue($this->packageKolab->skus->contains($this->skuStorage));
    }

    public function testSkusLite()
    {
        // this is without a package quantity manipulator, just unique SKUs
        $this->assertCount(2, $this->packageLite->skus);
    }
}
