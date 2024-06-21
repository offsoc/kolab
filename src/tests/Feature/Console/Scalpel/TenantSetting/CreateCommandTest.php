<?php

namespace Tests\Feature\Console\Scalpel\TenantSetting;

use Tests\TestCase;

class CreateCommandTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        \App\TenantSetting::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        \App\TenantSetting::truncate();

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $tenant = \App\Tenant::whereNotIn('id', [1])->first();

        $this->artisan("scalpel:tenant-setting:create --key=test --value=init --tenant_id={$tenant->id}")
             ->assertExitCode(0);

        $setting = $tenant->settings()->where('key', 'test')->first();

        $this->assertSame('init', $setting->value);
        $this->assertSame('init', $tenant->fresh()->getSetting('test'));
    }
}
