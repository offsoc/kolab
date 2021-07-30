<?php

namespace Tests\Feature\Console\Tenant;

use App\TenantSetting;
use Tests\TestCase;

class ListSettingsTest extends TestCase
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
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("tenant:list-settings unknown");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Unable to find the tenant.", $output);

        $tenant = \App\Tenant::whereNotIn('id', [1])->first();

        // A tenant without settings
        $code = \Artisan::call("tenant:list-settings {$tenant->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $tenant->setSetting('app.test1', 'test1');
        $tenant->setSetting('app.test2', 'test2');

        // A tenant with some settings
        $expected = "app.test1: test1\napp.test2: test2";

        $code = \Artisan::call("tenant:list-settings {$tenant->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame($expected, $output);
    }
}
