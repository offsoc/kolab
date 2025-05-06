<?php

namespace Tests\Feature\Jobs\Mail;

use App\Jobs\Mail\SignupInvitationJob;
use App\Mail\SignupInvitation;
use App\SignupInvitation as SI;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignupInvitationJobTest extends TestCase
{
    private $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->invitation = SI::create(['email' => 'SignupInvitationEmailTest@external.com']);
    }

    protected function tearDown(): void
    {
        $this->invitation->delete();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new SignupInvitationJob($this->invitation);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(SignupInvitation::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(SignupInvitation::class, function ($mail) {
            return $mail->hasTo($this->invitation->email);
        });

        $this->assertTrue($this->invitation->isSent());
    }
}
