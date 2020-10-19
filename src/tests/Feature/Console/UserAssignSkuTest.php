<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class UserAssignSkuTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('add-entitlement@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('add-entitlement@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $sku = \App\Sku::where('title', 'meet')->first();
        $user = $this->getTestUser('add-entitlement@kolabnow.com');

        $this->artisan('user:assign-sku unknown@unknown.org ' . $sku->id)
             ->assertExitCode(1)
             ->expectsOutput("Unable to find the user unknown@unknown.org.");

        $this->artisan('user:assign-sku ' . $user->email . ' unknownsku')
             ->assertExitCode(1)
             ->expectsOutput("Unable to find the SKU unknownsku.");

        $this->artisan('user:assign-sku ' . $user->email . ' ' . $sku->id)
             ->assertExitCode(0);

        $this->assertCount(1, $user->entitlements()->where('sku_id', $sku->id)->get());

        // Try again (also test sku by title)
        $this->artisan('user:assign-sku ' . $user->email . ' ' . $sku->title)
             ->assertExitCode(1)
             ->expectsOutput("The entitlement already exists. Maybe try with --qty=X?");

        $this->assertCount(1, $user->entitlements()->where('sku_id', $sku->id)->get());

        // Try again with --qty option, to force the assignment
        $this->artisan('user:assign-sku ' . $user->email . ' ' . $sku->title . ' --qty=1')
             ->assertExitCode(0);

        $this->assertCount(2, $user->entitlements()->where('sku_id', $sku->id)->get());
    }
}
