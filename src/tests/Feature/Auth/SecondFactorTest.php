<?php

namespace Tests\Feature\Auth;

use App\Auth\SecondFactor;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;

class SecondFactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('entitlement-test@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test that 2FA config is removed from Roundcube database
     * on entitlement delete
     */
    public function testEntitlementDelete(): void
    {
        // Create the user, and assign 2FA to him, and add Roundcube setup
        $sku_2fa = Sku::withEnvTenantContext()->where('title', '2fa')->first();
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $user->assignSku($sku_2fa);
        SecondFactor::seed('entitlement-test@kolabnow.com');

        $entitlement = Entitlement::where('sku_id', $sku_2fa->id)
            ->where('entitleable_id', $user->id)
            ->first();

        $this->assertTrue(!empty($entitlement));

        $sf = new SecondFactor($user);
        $factors = $sf->factors();

        $this->assertCount(1, $factors);
        $this->assertSame('totp:8132a46b1f741f88de25f47e', $factors[0]);
        // $this->assertSame('dummy:dummy', $factors[1]);

        // Delete the entitlement, expect all configured 2FA methods in Roundcube removed
        $entitlement->delete();

        $this->assertTrue($entitlement->trashed());

        $sf = new SecondFactor($user);
        $factors = $sf->factors();

        $this->assertCount(0, $factors);
    }
}
