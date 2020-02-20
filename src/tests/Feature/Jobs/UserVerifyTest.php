<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UserVerify;
use App\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserVerifyTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('new-job-user@' . \config('app.domain'));
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('new-job-user@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        $this->assertFalse($user->isImapReady());

        $job = new UserVerify($user);
        $job->handle();

        $this->assertTrue($user->fresh()->isImapReady() === false);
    }
}
