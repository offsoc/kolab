<?php

namespace Tests\Feature;

use App\Tenant;
use App\TenantSetting;
use Tests\TestCase;

class TenantTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        TenantSetting::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        TenantSetting::truncate();

        parent::tearDown();
    }

    /**
     * Test Tenant::getConfig() method
     */
    public function testGetConfig(): void
    {
        // No tenant id specified
        $this->assertSame(\config('app.name'), Tenant::getConfig(null, 'app.name'));
        $this->assertSame(\config('app.env'), Tenant::getConfig(null, 'app.env'));
        $this->assertSame(null, Tenant::getConfig(null, 'app.unknown'));

        $tenant = Tenant::whereNotIn('id', [1])->first();
        $tenant->setSetting('app.test', 'test');

        // Tenant specified
        $this->assertSame($tenant->title, Tenant::getConfig($tenant->id, 'app.name'));
        $this->assertSame('test', Tenant::getConfig($tenant->id, 'app.test'));
        $this->assertSame(\config('app.env'), Tenant::getConfig($tenant->id, 'app.env'));
        $this->assertSame(null, Tenant::getConfig($tenant->id, 'app.unknown'));
    }

    /**
     * Test Tenant::wallet() method
     */
    public function testWallet(): void
    {
        $tenant = Tenant::find(1);
        $user = \App\User::where('email', 'reseller@' . \config('app.domain'))->first();

        $wallet = $tenant->wallet();

        $this->assertInstanceof(\App\Wallet::class, $wallet);
        $this->assertSame($user->wallets->first()->id, $wallet->id);
    }
}
