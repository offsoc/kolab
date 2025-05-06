<?php

namespace Tests\Feature\Console\AuthAttempt;

use App\AuthAttempt;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases

        $user = $this->getTestUser('john@kolab.org');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, "10.0.0.1");
        $code = \Artisan::call("authattempt:delete {$authAttempt->id}");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertTrue(!AuthAttempt::find($authAttempt->id));

        // AuthAttempt not existing
        $code = \Artisan::call("authattempt:delete 999");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("No such authattempt 999", $output);
    }
}
