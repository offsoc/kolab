<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PaymentMandateDisabledEmail;
use App\Mail\PaymentMandateDisabled;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentMandateDisabledEmailTest extends TestCase
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
        $wallet = $user->wallets()->first();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PaymentMandateDisabledEmail($wallet);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentMandateDisabled::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentMandateDisabled::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->hasCc('ext@email.tld');
        });
    }
}
