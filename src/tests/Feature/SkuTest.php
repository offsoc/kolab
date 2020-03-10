<?php

namespace Tests\Feature;

use App\Entitlement;
use App\Handlers;
use App\Package;
use App\Sku;
use Carbon\Carbon;
use Tests\TestCase;

class SkuTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');

        parent::tearDown();
    }

    public function testPackageEntitlements(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');

        $wallet = $user->wallets()->first();

        $package = Package::where('title', 'lite')->first();

        $sku_mailbox = Sku::where('title', 'mailbox')->first();
        $sku_storage = Sku::where('title', 'storage')->first();

        $user = $user->assignPackage($package);

        $this->backdateEntitlements($user->fresh()->entitlements, Carbon::now()->subMonths(1));

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->balance < 0);
    }

    public function testSkuEntitlements(): void
    {
        $this->assertCount(3, Sku::where('title', 'mailbox')->first()->entitlements);
    }

    public function testSkuPackages(): void
    {
        $this->assertCount(2, Sku::where('title', 'mailbox')->first()->packages);
    }

    public function testSkuHandlerDomainHosting(): void
    {
        $sku = Sku::where('title', 'domain-hosting')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\DomainHosting::entitleableClass(),
            $entitlement->entitleable_type
        );
    }

    public function testSkuHandlerMailbox(): void
    {
        $sku = Sku::where('title', 'mailbox')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\Mailbox::entitleableClass(),
            $entitlement->entitleable_type
        );
    }

    public function testSkuHandlerStorage(): void
    {
        $sku = Sku::where('title', 'storage')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\Storage::entitleableClass(),
            $entitlement->entitleable_type
        );
    }
}
