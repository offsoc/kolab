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

        User::where('email', 'new-job-user@' . \config('app.domain'))->delete();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('new-job-user@' . \config('app.domain'));

        $this->assertFalse($user->isImapReady());

        $mock = \Mockery::mock('alias:App\Backends\IMAP');
        $mock->shouldReceive('verifyAccount')
            ->once()
            ->with($user->email)
            ->andReturn(false);

        $job = new UserVerify($user);
        $job->handle();

        $this->assertTrue($user->fresh()->isImapReady() === false);

        $mock->shouldReceive('verifyAccount')
            ->once()
            ->with($user->email)
            ->andReturn(true);

        $job->handle();

        $this->assertTrue($user->fresh()->isImapReady());
    }
}
