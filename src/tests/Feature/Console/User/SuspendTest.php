<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SuspendTest extends TestCase
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
        $code = \Artisan::call("user:suspend unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('user@force-delete.com');

        // Test success
        $code = \Artisan::call("user:suspend {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertTrue($user->fresh()->isSuspended());
    }
}
