<?php

namespace Tests\Feature\Jobs\User;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $ned = $this->getTestUser('ned@kolab.org');
        $ned->status |= User::STATUS_IMAP_READY;
        $ned->save();
    }

    /**
     * {@inheritDoc}
     */
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

        // Test non-existing user ID
        $job = new \App\Jobs\User\VerifyJob(123);
        $job->handle();

        $this->assertTrue($job->hasFailed());
        $this->assertSame("User 123 could not be found in the database.", $job->failureMessage);

        // Test existing user
        $user = $this->getTestUser('ned@kolab.org');

        if ($user->isImapReady()) {
            $user->status ^= User::STATUS_IMAP_READY;
            $user->save();
        }

        $this->assertFalse($user->isImapReady());

        for ($i = 0; $i < 10; $i++) {
            $job = new \App\Jobs\User\VerifyJob($user->id);
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
