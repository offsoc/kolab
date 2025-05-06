<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnsuspendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:unsuspend unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('user@force-delete.com');
        $user->suspend();

        // Test success
        $code = \Artisan::call("user:unsuspend {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertFalse($user->fresh()->isSuspended());
    }
}
