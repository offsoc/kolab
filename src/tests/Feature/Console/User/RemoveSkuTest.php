<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class RemoveSkuTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('remove-entitlement@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('remove-entitlement@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $storage = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $user = $this->getTestUser('remove-entitlement@kolabnow.com');

        // Unknown user
        $this->artisan("user:remove-sku unknown@unknown.org {$storage->id}")
             ->assertExitCode(1)
             ->expectsOutput("User not found.");

        // Unknown SKU
        $this->artisan("user:remove-sku {$user->email} unknownsku")
             ->assertExitCode(1)
             ->expectsOutput("Unable to find the SKU unknownsku.");

        // Invalid quantity
        $this->artisan("user:remove-sku {$user->email} {$storage->id} --qty=5")
             ->assertExitCode(1)
             ->expectsOutput("There aren't that many entitlements.");

        $user->assignSku($storage, 80);
        $entitlements = $user->entitlements()->where('sku_id', $storage->id);
        $this->assertSame(80, $entitlements->count());

        // Remove single entitlement
        $this->artisan("user:remove-sku {$user->email} {$storage->title}")
             ->assertExitCode(0);

        $this->assertSame(79, $entitlements->count());

        // Mass removal
        $start = microtime(true);
        $this->artisan("user:remove-sku {$user->email} {$storage->id} --qty=78")
             ->assertExitCode(0);

        // 5GB is free, so it should stay at 5
        $this->assertSame(5, $entitlements->count());
        $this->assertThat(microtime(true) - $start, $this->lessThan(2));
    }
}
