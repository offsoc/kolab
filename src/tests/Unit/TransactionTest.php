<?php

namespace Tests\Unit;

use App\Entitlement;
use App\Sku;
use App\Transaction;
use App\Wallet;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    /**
     * Test transaction short and long labels
     */
    public function testLabels(): void
    {
        // Prepare test environment
        Transaction::where('amount', '<', 20)->delete();
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Create transactions

        $transaction = Transaction::create([
                'object_id' => $wallet->id,
                'object_type' => Wallet::class,
                'type' => Transaction::WALLET_PENALTY,
                'amount' => -10,
                'description' => "A test penalty"
        ]);

        $transaction = Transaction::create([
                'object_id' => $wallet->id,
                'object_type' => Wallet::class,
                'type' => Transaction::WALLET_DEBIT,
                'amount' => -9
        ]);

        $transaction = Transaction::create([
                'object_id' => $wallet->id,
                'object_type' => Wallet::class,
                'type' => Transaction::WALLET_CREDIT,
                'amount' => 11
        ]);

        $transaction = Transaction::create([
                'object_id' => $wallet->id,
                'object_type' => Wallet::class,
                'type' => Transaction::WALLET_AWARD,
                'amount' => 12,
                'description' => "A test award"
        ]);

        $sku = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $entitlement = Entitlement::where('sku_id', $sku->id)->first();
        $transaction = Transaction::create([
                'user_email' => 'test@test.com',
                'object_id' => $entitlement->id,
                'object_type' => Entitlement::class,
                'type' => Transaction::ENTITLEMENT_CREATED,
                'amount' => 13
        ]);

        $sku = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $entitlement = Entitlement::where('sku_id', $sku->id)->first();
        $transaction = Transaction::create([
                'user_email' => 'test@test.com',
                'object_id' => $entitlement->id,
                'object_type' => Entitlement::class,
                'type' => Transaction::ENTITLEMENT_BILLED,
                'amount' => 14
        ]);

        $sku = Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $entitlement = Entitlement::where('sku_id', $sku->id)->first();
        $transaction = Transaction::create([
                'user_email' => 'test@test.com',
                'object_id' => $entitlement->id,
                'object_type' => Entitlement::class,
                'type' => Transaction::ENTITLEMENT_DELETED,
                'amount' => 15
        ]);

        $transactions = Transaction::where('amount', '<', 20)->orderBy('amount')->get();

        $this->assertSame(-10, $transactions[0]->amount);
        $this->assertSame(Transaction::WALLET_PENALTY, $transactions[0]->type);
        $this->assertSame(
            "The balance of Default wallet was reduced by 0,10 CHF; A test penalty",
            $transactions[0]->toString()
        );
        $this->assertSame(
            "Charge: A test penalty",
            $transactions[0]->shortDescription()
        );

        $this->assertSame(-9, $transactions[1]->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $transactions[1]->type);
        $this->assertSame(
            "0,09 CHF was deducted from the balance of Default wallet",
            $transactions[1]->toString()
        );
        $this->assertSame(
            "Deduction",
            $transactions[1]->shortDescription()
        );

        $this->assertSame(11, $transactions[2]->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $transactions[2]->type);
        $this->assertSame(
            "0,11 CHF was added to the balance of Default wallet",
            $transactions[2]->toString()
        );
        $this->assertSame(
            "Payment",
            $transactions[2]->shortDescription()
        );

        $this->assertSame(12, $transactions[3]->amount);
        $this->assertSame(Transaction::WALLET_AWARD, $transactions[3]->type);
        $this->assertSame(
            "Bonus of 0,12 CHF awarded to Default wallet; A test award",
            $transactions[3]->toString()
        );
        $this->assertSame(
            "Bonus: A test award",
            $transactions[3]->shortDescription()
        );

        $ent = $transactions[4]->entitlement();
        $this->assertSame(13, $transactions[4]->amount);
        $this->assertSame(Transaction::ENTITLEMENT_CREATED, $transactions[4]->type);
        $this->assertSame(
            "test@test.com created mailbox for " . $ent->entitleable->toString(),
            $transactions[4]->toString()
        );
        $this->assertSame(
            "Added mailbox for " . $ent->entitleable->toString(),
            $transactions[4]->shortDescription()
        );

        $ent = $transactions[5]->entitlement();
        $this->assertSame(14, $transactions[5]->amount);
        $this->assertSame(Transaction::ENTITLEMENT_BILLED, $transactions[5]->type);
        $this->assertSame(
            sprintf("%s for %s is billed at 0,14 CHF", $ent->sku->title, $ent->entitleable->toString()),
            $transactions[5]->toString()
        );
        $this->assertSame(
            sprintf("Billed %s for %s", $ent->sku->title, $ent->entitleable->toString()),
            $transactions[5]->shortDescription()
        );

        $ent = $transactions[6]->entitlement();
        $this->assertSame(15, $transactions[6]->amount);
        $this->assertSame(Transaction::ENTITLEMENT_DELETED, $transactions[6]->type);
        $this->assertSame(
            sprintf("test@test.com deleted %s for %s", $ent->sku->title, $ent->entitleable->toString()),
            $transactions[6]->toString()
        );
        $this->assertSame(
            sprintf("Deleted %s for %s", $ent->sku->title, $ent->entitleable->toString()),
            $transactions[6]->shortDescription()
        );
    }

    /**
     * Test that an exception is being thrown on invalid type
     */
    public function testInvalidType(): void
    {
        $this->expectException(\Exception::class);

        $transaction = Transaction::create(
            [
                'object_id' => 'fake-id',
                'object_type' => Wallet::class,
                'type' => 'invalid',
                'amount' => 9
            ]
        );
    }

    public function testEntitlementForWallet(): void
    {
        $transaction = \App\Transaction::where('object_type', \App\Wallet::class)
            ->whereIn('object_id', \App\Wallet::pluck('id'))->first();

        $entitlement = $transaction->entitlement();
        $this->assertNull($entitlement);
        $this->assertNotNull($transaction->wallet());
    }

    public function testWalletForEntitlement(): void
    {
        $transaction = \App\Transaction::where('object_type', \App\Entitlement::class)
            ->whereIn('object_id', \App\Entitlement::pluck('id'))->first();

        $wallet = $transaction->wallet();
        $this->assertNull($wallet);

        $this->assertNotNull($transaction->entitlement());
    }
}
