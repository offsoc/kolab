<?php

namespace Tests\Feature\Console\Scalpel\TenantSetting;

use Tests\TestCase;

class UpdateCommandTest extends TestCase
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

    public function testHandle(): void
    {
        $this->artisan("scalpel:tenant-setting:update unknown --value=test")
             ->assertExitCode(1)
             ->expectsOutput("No such tenant-setting unknown");

        $tenant = \App\Tenant::whereNotIn('id', [1])->first();
        $tenant->setSetting('test', 'test-old');
        $setting = $tenant->settings()->where('key', 'test')->first();

        $this->assertSame('test-old', $setting->value);

        $this->artisan("scalpel:tenant-setting:update {$setting->id} --value=test")
             ->assertExitCode(0);

        $this->assertSame('test', $setting->fresh()->value);
        $this->assertSame('test', $tenant->fresh()->getSetting('test'));
    }
}
