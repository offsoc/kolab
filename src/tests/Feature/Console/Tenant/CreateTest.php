<?php

namespace Tests\Feature\Console\Tenant;

use App\Domain;
use App\Package;
use App\Plan;
use App\Sku;
use App\Tenant;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTest extends TestCase
{
    private $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('unknown@user.com');
        $this->deleteTestDomain('tenant.com');
        $this->deleteTestDomain('user.com');
        Tenant::where('title', 'Test Tenant')->delete();
    }

    protected function tearDown(): void
    {
        if ($this->tenantId) {
            Queue::fake();

            User::where('tenant_id', $this->tenantId)->forceDelete();
            Plan::where('tenant_id', $this->tenantId)->delete();
            Package::where('tenant_id', $this->tenantId)->delete();
            Sku::where('tenant_id', $this->tenantId)->delete();
            Domain::where('tenant_id', $this->tenantId)->delete();
            Tenant::find($this->tenantId)->delete();
        }
        $this->deleteTestUser('unknown@user.com');

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

        // User already existing
        $user = $this->getTestUser('unknown@user.com');
        $code = \Artisan::call("tenant:create {$user->email} user.com --title=\"Test Tenant\"");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);

        // User not existing
        $code = \Artisan::call("tenant:create test-tenant@tenant.com tenant.com --title=\"Test Tenant\"");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression("/^Created tenant [0-9]+./", $output);

        preg_match("/Created tenant ([0-9]+)./", $output, $matches);
        $this->tenantId = $matches[1];
        $tenant = Tenant::find($this->tenantId);
        $this->assertNotEmpty($tenant);
        $this->assertSame('Test Tenant', $tenant->title);

        preg_match("/Created user ([0-9]+)./", $output, $matches);
        $userId = $matches[1];
        $user = User::find($userId);
        $this->assertNotEmpty($user);
        $this->assertSame('reseller', $user->role);
        $this->assertSame($tenant->id, $user->tenant_id);

        // Assert cloned SKUs
        $skus = Sku::where('tenant_id', \config('app.tenant_id'))->where('active', true);

        $skus->each(function ($sku) use ($tenant) {
            $sku_new = Sku::where('tenant_id', $tenant->id)
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
