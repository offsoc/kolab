<?php

namespace Tests\Feature;

use App\Handlers;
use App\Package;
use App\Sku;
use App\Tenant;
use Carbon\Carbon;
use Tests\TestCase;

class SkuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');

        parent::tearDown();
    }

    public function testPackageEntitlements(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');

        $wallet = $user->wallets()->first();

        $package = Package::withEnvTenantContext()->where('title', 'lite')->first();

        $user = $user->assignPackage($package);

        $this->backdateEntitlements($user->fresh()->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->balance < 0);
    }

    public function testSkuEntitlements(): void
    {
        $this->assertCount(5, Sku::withEnvTenantContext()->where('title', 'mailbox')->first()->entitlements);
    }

    public function testSkuPackages(): void
    {
        $this->assertCount(2, Sku::withEnvTenantContext()->where('title', 'mailbox')->first()->packages);
    }

    public function testSkuHandlerDomainHosting(): void
    {
        $sku = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\DomainHosting::entitleableClass(),
            $entitlement->entitleable_type
        );
    }

    public function testSkuHandlerMailbox(): void
    {
        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\Mailbox::entitleableClass(),
            $entitlement->entitleable_type
        );
    }

    public function testSkuHandlerStorage(): void
    {
        $sku = Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $entitlement = $sku->entitlements->first();

        $this->assertSame(
            Handlers\Storage::entitleableClass(),
            $entitlement->entitleable_type
        );
    }

    public function testSkuTenant(): void
    {
        $sku = Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $tenant = $sku->tenant()->first();

        $this->assertInstanceof(Tenant::class, $tenant);

        $tenant = $sku->tenant;

        $this->assertInstanceof(Tenant::class, $tenant);
    }
}
