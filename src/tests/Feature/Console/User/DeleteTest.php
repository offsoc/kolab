<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class DeleteTest extends TestCase
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
     * Test the command
     */
    public function testHandle(): void
    {
        // Non-existing user
        $code = \Artisan::call("user:delete unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $user = $this->getTestUser('user@force-delete.com');

        // Test success
        $code = \Artisan::call("user:delete {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("", $output);
        $this->assertTrue($user->fresh()->trashed());

        // User already deleted
        $code = \Artisan::call("user:delete {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user user@force-delete.com", $output);
    }
}
