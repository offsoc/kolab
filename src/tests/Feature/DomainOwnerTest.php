<?php

namespace Tests\Feature;

use App\Package;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainOwnerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolab.org');
    }

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

        $package = Package::where('title', 'kolab')->first();

        $john->assignPackage($package, $jane);

        // assert jane has a mailbox entitlement
        $this->assertTrue($jane->entitlements->count() == 4);
    }
}
