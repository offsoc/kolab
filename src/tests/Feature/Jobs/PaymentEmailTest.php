<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PaymentEmail;
use App\Mail\PaymentFailure;
use App\Mail\PaymentSuccess;
use App\Payment;
use App\Providers\PaymentProvider;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentEmailTest extends TestCase
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

        $payment = new Payment();
        $payment->id = 'test-payment';
        $payment->wallet_id = $wallet->id;
        $payment->amount = 100;
        $payment->status = PaymentProvider::STATUS_PAID;
        $payment->description = 'test';
        $payment->provider = 'stripe';
        $payment->type = PaymentProvider::TYPE_ONEOFF;
        $payment->save();

        Mail::fake();

        // Assert that no jobs were pushed...
        Mail::assertNothingSent();

        $job = new PaymentEmail($payment);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentSuccess::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentSuccess::class, function ($mail) {
            return $mail->hasTo('ext@email.tld') && !$mail->hasCc('ext@email.tld');
        });

        $payment->status = PaymentProvider::STATUS_FAILED;
        $payment->save();

        $job = new PaymentEmail($payment);
        $job->handle();

        // Assert the email sending job was pushed once
        Mail::assertSent(PaymentFailure::class, 1);

        // Assert the mail was sent to the user's email
        Mail::assertSent(PaymentFailure::class, function ($mail) {
            return $mail->hasTo('ext@email.tld') && !$mail->hasCc('ext@email.tld');
        });

        $payment->status = PaymentProvider::STATUS_EXPIRED;
        $payment->save();

        $job = new PaymentEmail($payment);
        $job->handle();

        // Assert the email sending job was pushed twice
        Mail::assertSent(PaymentFailure::class, 2);

        // None of statuses below should trigger an email
        Mail::fake();

        $states = [
            PaymentProvider::STATUS_OPEN,
            PaymentProvider::STATUS_CANCELED,
            PaymentProvider::STATUS_PENDING,
            PaymentProvider::STATUS_AUTHORIZED,
        ];

        foreach ($states as $state) {
            $payment->status = $state;
            $payment->save();

            $job = new PaymentEmail($payment);
            $job->handle();
        }

        // Assert that no mailables were sent...
        Mail::assertNothingSent();
    }
}
