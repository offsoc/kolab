<?php

namespace Tests\Feature\Console\Scalpel\TenantSetting;

use App\Tenant;
use App\TenantSetting;
use Tests\TestCase;

class CreateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TenantSetting::truncate();
    }

    protected function tearDown(): void
    {
        TenantSetting::truncate();

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $tenant = Tenant::whereNotIn('id', [1])->first();

        $this->artisan("scalpel:tenant-setting:create --key=test --value=init --tenant_id={$tenant->id}")
            ->assertExitCode(0);

        $setting = $tenant->settings()->where('key', 'test')->first();

        $this->assertSame('init', $setting->value);
        $this->assertSame('init', $tenant->fresh()->getSetting('test'));
    }
}
