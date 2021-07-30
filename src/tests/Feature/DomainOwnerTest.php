<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainOwnerTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolab.org');

        parent::tearDown();
    }

    public function testJohnCreateJane(): void
    {
        $john = User::where('email', 'john@kolab.org')->first();

        $jane = User::create(
            [
                'name' => 'Jane Doe',
                'email' => 'jane@kolab.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );

        $package = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $mailbox_sku = \App\Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        $john->assignPackage($package, $jane);

        // assert jane has a mailbox entitlement
        $this->assertCount(7, $jane->entitlements);
        $this->assertCount(1, $jane->entitlements()->where('sku_id', $mailbox_sku->id)->get());
    }
}
