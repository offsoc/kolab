<?php

namespace Tests\Feature\Console\User;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatusTest extends TestCase
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

        $user = $this->getTestUser(
            'user@force-delete.com',
            ['status' => User::STATUS_NEW | User::STATUS_ACTIVE | User::STATUS_IMAP_READY | User::STATUS_LDAP_READY]
        );

        // Existing user
        $code = \Artisan::call("user:status {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Status (51): new (1), active (2), ldapReady (16), imapReady (32)", $output);

        $user->status = User::STATUS_ACTIVE;
        $user->save();
        $user->delete();

        // Deleted user
        $code = \Artisan::call("user:status {$user->email}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("Status (2): active (2)", $output);
    }
}
