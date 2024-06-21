<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DegradeTest extends TestCase
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
        $code = \Artisan::call("user:degrade unknown@unknown.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Create a user account for degrade
        $user = $this->getTestUser('user-degrade-test@kolabnow.com');

        $code = \Artisan::call("user:degrade {$user->email}");
        $output = trim(\Artisan::output());

        $user->refresh();

        $this->assertTrue($user->isDegraded());
        $this->assertSame('', $output);
        $this->assertSame(0, $code);
    }
}
