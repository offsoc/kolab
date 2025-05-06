<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RestrictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-restrict-test@kolabnow.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('user-restrict-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:restrict unknown@unknown.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Create a user account for degrade
        $user = $this->getTestUser('user-restrict-test@kolabnow.com');

        $this->assertFalse($user->isRestricted());

        $code = \Artisan::call("user:restrict {$user->email}");
        $output = trim(\Artisan::output());

        $user->refresh();

        $this->assertTrue($user->isRestricted());
        $this->assertSame('', $output);
        $this->assertSame(0, $code);
    }
}
