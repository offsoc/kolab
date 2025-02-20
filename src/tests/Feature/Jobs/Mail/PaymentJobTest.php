<?php

namespace Tests\Feature\Jobs\Mail;

use App\Jobs\Mail\PaymentJob;
use App\Mail\PaymentFailure;
use App\Mail\PaymentSuccess;
use App\Payment;
use App\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentJobTest extends TestCase
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

        $payment = new Payment();
        $payment->id = 'test-payment';
        $payment->wallet_id = $wallet->id;
        $payment->amount = 100;
        $payment->credit_amount = 100;
        $payment->currency_amount = 100;
        $payment->currency = 'CHF';
        $payment->status = Payment::STATUS_PAID;
        $payment->description = 'test';
        $payment->provider = 'stripe';
        $payment->type = Payment::TYPE_ONEOFF;
        $payment->save();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PaymentJob($payment);
        $job->handle();

        $this->assertSame(PaymentJob::QUEUE, $job->queue);

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentSuccess::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentSuccess::class, function ($mail) {
            return $mail->hasTo('ext@email.tld') && !$mail->hasCc('ext@email.tld');
        });

        $payment->status = Payment::STATUS_FAILED;
        $payment->save();

        $job = new PaymentJob($payment);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentFailure::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentFailure::class, function ($mail) {
            return $mail->hasTo('ext@email.tld') && !$mail->hasCc('ext@email.tld');
        });

        $payment->status = Payment::STATUS_EXPIRED;
        $payment->save();

        $job = new PaymentJob($payment);
        $job->handle();

        // Assert the email sending job was pushed twice
        Mail::assertSent(PaymentFailure::class, 2);

        // None of statuses below should trigger an email
        Mail::fake();

        $states = [
            Payment::STATUS_OPEN,
            Payment::STATUS_CANCELED,
            Payment::STATUS_PENDING,
            Payment::STATUS_AUTHORIZED,
        ];

        foreach ($states as $state) {
            $payment->status = $state;
            $payment->save();

            $job = new PaymentJob($payment);
            $job->handle();
        }

        // Assert that no mailables were sent...
        Mail::assertNothingSent();
    }
}
