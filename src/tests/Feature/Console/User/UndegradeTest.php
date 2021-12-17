<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UndegradeTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-degrade-test@kolabnow.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user-degrade-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:undegrade unknown@unknown.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Create a user account for degrade/undegrade
        $user = $this->getTestUser('user-degrade-test@kolabnow.com', ['status' => \App\User::STATUS_DEGRADED]);

        $this->assertTrue($user->isDegraded());

        $code = \Artisan::call("user:undegrade {$user->email}");
        $output = trim(\Artisan::output());

        $user->refresh();

        $this->assertFalse($user->isDegraded());
        $this->assertSame('', $output);
        $this->assertSame(0, $code);
    }
}
