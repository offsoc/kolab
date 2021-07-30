<?php

namespace Tests\Feature\Console\Tenant;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
{
    private $tenantId;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('test-tenant@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if ($this->tenantId) {
            Queue::fake();

            \App\User::where('tenant_id', $this->tenantId)->forceDelete();
            \App\Plan::where('tenant_id', $this->tenantId)->delete();
            \App\Package::where('tenant_id', $this->tenantId)->delete();
            \App\Sku::where('tenant_id', $this->tenantId)->delete();
            \App\Tenant::find($this->tenantId)->delete();
        }

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases

        // User not existing
        $code = \Artisan::call("tenant:create unknown@user.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('test-tenant@kolabnow.com');

        $this->assertEmpty($user->role);
        $this->assertEquals($user->tenant_id, \config('app.tenant_id'));

        // User not existing
        $code = \Artisan::call("tenant:create {$user->email} --title=\"Test Tenant\"");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertRegExp("/^Created tenant [0-9]+./", $output);

        preg_match("/^Created tenant ([0-9]+)./", $output, $matches);
        $this->tenantId = $matches[1];

        $tenant = \App\Tenant::find($this->tenantId);
        $user->refresh();

        $this->assertNotEmpty($tenant);
        $this->assertSame('Test Tenant', $tenant->title);
        $this->assertSame('reseller', $user->role);
        $this->assertSame($tenant->id, $user->tenant_id);

        // Assert cloned SKUs
        $skus = \App\Sku::where('tenant_id', \config('app.tenant_id'))->where('active', true);

        $skus->each(function ($sku) use ($tenant) {
            $sku_new = \App\Sku::where('tenant_id', $tenant->id)
                ->where('title', $sku->title)->get();

            $this->assertSame(1, $sku_new->count());
            $sku_new = $sku_new->first();
            $this->assertSame($sku->name, $sku_new->name);
            $this->assertSame($sku->description, $sku_new->description);
            $this->assertSame($sku->cost, $sku_new->cost);
            $this->assertSame($sku->units_free, $sku_new->units_free);
            $this->assertSame($sku->period, $sku_new->period);
            $this->assertSame($sku->handler_class, $sku_new->handler_class);
            $this->assertNotEmpty($sku_new->active);
        });

        // TODO: Plans, packages
    }
}
