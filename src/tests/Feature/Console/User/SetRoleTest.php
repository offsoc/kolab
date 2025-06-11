<?php

namespace Tests\Feature\Console\User;

use App\User;
use Tests\TestCase;

class SetRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('wallets-controller@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('wallets-controller@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('wallets-controller@kolabnow.com');

        $this->assertNull($user->role);

        // Invalid user id
        $code = \Artisan::call("user:set-role 123 admin");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame('User not found.', $output);

        // Invalid role
        $code = \Artisan::call("user:set-role {$user->id} 123");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame('Invalid role.', $output);

        // Assign a role
        $code = \Artisan::call("user:set-role {$user->id} " . User::ROLE_ADMIN);
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertSame(User::ROLE_ADMIN, $user->fresh()->role);

        // Remove a role
        $code = \Artisan::call("user:set-role {$user->id} null");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('Removing role.', $output);
        $this->assertNull($user->fresh()->role);
    }
}
