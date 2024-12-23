<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SignupInvitationEmail;
use App\Mail\SignupInvitation;
use App\SignupInvitation as SI;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignupInvitationEmailTest extends TestCase
{
    private $invitation;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->invitation = SI::create(['email' => 'SignupInvitationEmailTest@external.com']);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->invitation->delete();
    }

    /**
     * Test job handle
     */
    public function testSignupInvitationEmailHandle(): void
    {
        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new SignupInvitationEmail($this->invitation);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(SignupInvitation::class, 1);

        // Assert the mail was sent to the code's email
        Mail::assertSent(SignupInvitation::class, function ($mail) {
            return $mail->hasTo($this->invitation->email);
        });

        $this->assertTrue($this->invitation->isSent());
    }

    /**
     * Test job failure handling
     */
    public function testSignupInvitationEmailFailure(): void
    {
        $this->markTestIncomplete();
    }
}
