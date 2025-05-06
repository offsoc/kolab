<?php

namespace Tests\Feature\Console\Scalpel\TenantSetting;

use App\Tenant;
use App\TenantSetting;
use Tests\TestCase;

class UpdateCommandTest extends TestCase
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

    public function testHandle(): void
    {
        $this->artisan("scalpel:tenant-setting:update unknown --value=test")
            ->assertExitCode(1)
            ->expectsOutput("No such tenant-setting unknown");

        $tenant = Tenant::whereNotIn('id', [1])->first();
        $tenant->setSetting('test', 'test-old');
        $setting = $tenant->settings()->where('key', 'test')->first();

        $this->assertSame('test-old', $setting->value);

        $this->artisan("scalpel:tenant-setting:update {$setting->id} --value=test")
            ->assertExitCode(0);

        $this->assertSame('test', $setting->fresh()->value);
        $this->assertSame('test', $tenant->fresh()->getSetting('test'));
    }
}
