<?php

namespace Tests\Feature;

use App\Tenant;
use Tests\TestCase;

class TenantTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test Tenant::wallet() method
     */
    public function testWallet(): void
    {
        $tenant = Tenant::find(1);
        $user = \App\User::where('email', 'reseller@kolabnow.com')->first();

        $wallet = $tenant->wallet();

        $this->assertInstanceof(\App\Wallet::class, $wallet);
        $this->assertSame($user->wallets->first()->id, $wallet->id);
    }
}
