<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UserCreate;
use App\Jobs\UserVerify;
use App\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserVerifyTest extends TestCase
{
    /**
     * Test job handle
     *
     * @group imap
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->status ^= User::STATUS_IMAP_READY;
        $user->save();

        $this->assertFalse($user->isImapReady());

        $job = new UserVerify($user);
        $job->handle();

        $this->assertTrue($user->fresh()->isImapReady());
    }
}
