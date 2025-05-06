<?php

namespace Tests\Feature\Console\AuthAttempt;

use App\AuthAttempt;
use Tests\TestCase;

class ListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AuthAttempt::truncate();
    }

    protected function tearDown(): void
    {
        AuthAttempt::truncate();
        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        // Warning: We're not using artisan() here, as this will not
        // allow us to test "empty output" cases
        $code = \Artisan::call('authattempts');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame('', $output);

        $user = $this->getTestUser('john@kolab.org');
        $authAttempt = AuthAttempt::recordAuthAttempt($user, '10.0.0.1');
        // For up-to date timestamps and whatnot
        $authAttempt->refresh();

        $code = \Artisan::call('authattempts');
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);

        $this->assertSame($authAttempt->toJson(\JSON_PRETTY_PRINT), $output);
    }
}
