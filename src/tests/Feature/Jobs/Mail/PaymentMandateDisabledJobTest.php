<?php

namespace Tests\Feature\Jobs\Mail;

use App\Jobs\Mail\PaymentMandateDisabledJob;
use App\Mail\PaymentMandateDisabled;
use App\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentMandateDisabledJobTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('PaymentEmail@UserAccount.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('PaymentEmail@UserAccount.com');

        parent::tearDown();
    }

    /**
     * Test job handle
     */
    public function testHandle(): void
    {
        $status = User::STATUS_ACTIVE | User::STATUS_LDAP_READY | User::STATUS_IMAP_READY;
        $user = $this->getTestUser('PaymentEmail@UserAccount.com', ['status' => $status]);
        $user->setSetting('external_email', 'ext@email.tld');
        $wallet = $user->wallets()->first();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PaymentMandateDisabledJob($wallet);
        $job->handle();

        $this->assertSame(PaymentMandateDisabledJob::QUEUE, $job->queue);

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentMandateDisabled::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentMandateDisabled::class, function ($mail) {
            return $mail->hasTo('ext@email.tld') && !$mail->hasCc('ext@email.tld');
        });
    }
}
