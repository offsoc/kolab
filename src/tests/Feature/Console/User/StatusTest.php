<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatusTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:status unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Existing user
        $code = \Artisan::call("user:status john@kolab.org");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Status (51): active (2), ldapReady (16), imapReady (32)", $output);

        $user = $this->getTestUser('user@force-delete.com');
        $user->delete();

        // Deleted user
        $code = \Artisan::call("user:status {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Status (3): active (2), deleted (8)", $output);
    }
}
