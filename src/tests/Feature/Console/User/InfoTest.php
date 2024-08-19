<?php

namespace Tests\Feature\Console\User;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InfoTest extends TestCase
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
        Queue::fake();

        // Non-existing user
        $code = \Artisan::call("user:info unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Test existing but soft-deleted user
        $user = $this->getTestUser('user@force-delete.com', ['status' => \App\User::STATUS_NEW]);
        $user->delete();

        $code = \Artisan::call("user:info {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("id: {$user->id}", $output);
        $this->assertStringContainsString("email: {$user->email}", $output);
        $this->assertStringContainsString("created_at: {$user->created_at}", $output);
        $this->assertStringContainsString("deleted_at: {$user->deleted_at}", $output);
        $this->assertStringContainsString("status: {$user->status}", $output);
        $this->assertStringContainsString("currency: CHF", $output);
    }
}
