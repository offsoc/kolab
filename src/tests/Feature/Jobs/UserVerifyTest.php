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

        $ned = $this->getTestUser('ned@kolab.org');
        $ned->status |= User::STATUS_IMAP_READY;
        $ned->save();
    }

    public function tearDown(): void
    {
        $ned = $this->getTestUser('ned@kolab.org');
        $ned->status |= User::STATUS_IMAP_READY;
        $ned->save();

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

        $user = $this->getTestUser('ned@kolab.org');
        $user->status ^= User::STATUS_IMAP_READY;
        $user->save();

        $this->assertFalse($user->isImapReady());

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
