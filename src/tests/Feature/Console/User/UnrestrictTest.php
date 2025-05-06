<?php

namespace Tests\Feature\Console\User;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UnrestrictTest extends TestCase
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
        $code = \Artisan::call("user:unrestrict unknown@unknown.org");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        $user = $this->getTestUser('user-restrict-test@kolabnow.com', ['status' => User::STATUS_RESTRICTED]);

        $this->assertTrue($user->isRestricted());

        $code = \Artisan::call("user:unrestrict {$user->email}");
        $output = trim(\Artisan::output());

        $user->refresh();

        $this->assertFalse($user->isRestricted());
        $this->assertSame('', $output);
        $this->assertSame(0, $code);
    }
}
