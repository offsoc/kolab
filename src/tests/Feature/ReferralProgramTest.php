<?php

namespace Tests\Feature;

use App\Payment;
use App\ReferralProgram;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReferralProgramTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('referrer@kolabnow.com');
        $this->deleteTestUser('referree@kolabnow.com');
        ReferralProgram::query()->delete();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('referrer@kolabnow.com');
        $this->deleteTestUser('referree@kolabnow.com');
        ReferralProgram::query()->delete();

        parent::tearDown();
    }

    /**
     * Tests for ReferralProgram::accounting() method
     */
    public function testAccountingWithAwardAmount(): void
    {
        Queue::fake();

        $referrer = $this->getTestUser('referrer@kolabnow.com');
        $referree = $this->getTestUser('referree@kolabnow.com');

        $referrer_wallet = $referrer->wallets()->first();
        $referree_wallet = $referree->wallets()->first();

        $program = ReferralProgram::create([
            'name' => "Test Referral",
            'description' => "Test Referral Description",
            'active' => true,
            'award_amount' => 1000,
            'award_percent' => 0,
            'payments_threshold' => 1000,
        ]);

        $code = $program->codes()->create(['user_id' => $referrer->id]);
        $referral = $code->referrals()->create(['user_id' => $referree->id]);

        // No payments yet
        ReferralProgram::accounting($referree);

        $referrer_wallet->refresh();
        $this->assertSame(0, $referrer_wallet->balance);

        Carbon::setTestNow(Carbon::createFromDate(2024, 2, 2));

        // A single payment below the threshold
        Payment::createFromArray([
            'id' => 'test-payment1',
            'amount' => 700,
            'currency' => $referree_wallet->currency,
            'currency_amount' => 700,
            'type' => Payment::TYPE_ONEOFF,
            'wallet_id' => $referree_wallet->id,
            'status' => Payment::STATUS_PAID,
        ]);

        ReferralProgram::accounting($referree);

        $referrer_wallet->refresh();
        $this->assertSame(0, $referrer_wallet->balance);

        // Two payments equal with the threshold
        $payment = Payment::createFromArray([
            'id' => 'test-payment2',
            'amount' => 300,
            'currency' => $referree_wallet->currency,
            'currency_amount' => 300,
            'type' => Payment::TYPE_ONEOFF,
            'wallet_id' => $referree_wallet->id,
            'status' => Payment::STATUS_PAID,
        ]);

        ReferralProgram::accounting($referree);

        $referrer_wallet->refresh();
        $referral->refresh();
        $redeemed_at = now()->format('Y-m-d H:i:s');
        $transaction = $referrer_wallet->transactions()->first();
        $this->assertSame($balance = 1000, $referrer_wallet->balance);
        $this->assertSame($redeemed_at, $referral->redeemed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Referral program award (' . $program->name . ')', $transaction->description);
        $this->assertSame(Transaction::WALLET_AWARD, $transaction->type);

        // Award redeemed already
        Carbon::setTestNow(Carbon::createFromDate(2024, 2, 4));
        ReferralProgram::accounting($referree);

        $referrer_wallet->refresh();
        $referral->refresh();
        $this->assertSame($balance, $referrer_wallet->balance);
        $this->assertSame($redeemed_at, $referral->redeemed_at->format('Y-m-d H:i:s'));

        // Test that Payment::credit() invokes ReferralProgram::accounting()
        $referral->redeemed_at = null;
        $referral->save();

        $payment->credit('TEST');

        $referrer_wallet->refresh();
        $referral->refresh();
        $this->assertSame($balance += 1000, $referrer_wallet->balance);
        $this->assertNotNull($referral->redeemed_at);

        // Test "closing" referrals if referrer is soft-deleted
        $referrer->delete();
        $referral->redeemed_at = null;
        $referral->save();

        ReferralProgram::accounting($referree);

        $referrer_wallet->refresh();
        $referral->refresh();
        $this->assertSame($balance, $referrer_wallet->balance);
        $this->assertSame($referral->redeemed_at->format('Y-m-d H:i:s'), $referral->created_at->format('Y-m-d H:i:s'));
    }
}
