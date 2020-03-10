<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UserCreate;
use App\Jobs\UserVerify;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserVerifyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @group imap
     */
    public function testHandle(): void
    {
        Queue::fake();

        $user = $this->getTestUser('jane@kolabnow.com');

        // This is a valid assertion in a feature, not functional test environment.
        $this->assertFalse($user->isImapReady());
        $this->assertFalse($user->isLdapReady());

        $job = new UserCreate($user);
        $job->handle();

        $this->assertFalse($user->isImapReady());
        $this->assertTrue($user->isLdapReady());

        for ($i = 0; $i < 10; $i++) {
            $job = new UserVerify($user);
            $job->handle();

            if ($user->fresh()->isImapReady()) {
                $this->assertTrue(true);
                return;
            }

            sleep(1);
        }

        $this->assertTrue(false, "Unable to verify the IMAP account is set up in time");
    }
}
