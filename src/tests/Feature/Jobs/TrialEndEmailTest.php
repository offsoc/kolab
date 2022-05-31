<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TrialEndEmail;
use App\Mail\TrialEnd;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrialEndEmailTest extends TestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('PaymentEmail@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('PaymentEmail@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     *
     * @return void
     */
    public function testHandle()
    {
        $user = $this->getTestUser('PaymentEmail@UserAccount.com');
        $user->setSetting('external_email', 'ext@email.tld');

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new TrialEndEmail($user);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(TrialEnd::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(TrialEnd::class, function ($mail) {
            return $mail->hasTo('paymentemail@useraccount.com') && !$mail->hasCc('ext@email.tld');
        });
    }
}
