<?php

namespace Tests\Feature\Jobs\User;

use App\Jobs\User\VerifyJob;
use App\Support\Facades\IMAP;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $ned = $this->getTestUser('ned@kolab.org');
        $ned->status |= User::STATUS_IMAP_READY;
        $ned->save();
    }

    protected function tearDown(): void
    {
        $ned = $this->getTestUser('ned@kolab.org');
        $ned->status |= User::STATUS_IMAP_READY;
        $ned->save();

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Test non-existing user ID
        $job = (new VerifyJob(123))->withFakeQueueInteractions();
        $job->handle();
        $job->assertFailedWith("User 123 could not be found in the database.");

        // Test existing user (verification successful)
        $user = $this->getTestUser('ned@kolab.org');

        if ($user->isImapReady()) {
            $user->status ^= User::STATUS_IMAP_READY;
            $user->save();
        }

        $this->assertFalse($user->isImapReady());

        IMAP::shouldReceive('verifyAccount')->once()->with($user->email)->andReturn(true);

        $job = new VerifyJob($user->id);
        $job->handle();

        $user->refresh();
        $this->assertTrue($user->isImapReady());

        // Test existing user (verification not-successful)
        $user->status ^= User::STATUS_IMAP_READY;
        $user->save();

        IMAP::shouldReceive('verifyAccount')->once()->with($user->email)->andReturn(false);

        $job = new VerifyJob($user->id);
        $job->handle();

        $this->assertFalse($user->fresh()->isImapReady());
    }
}
