<?php

namespace Tests\Feature;

use App\Package;
use App\User;
use App\Sku;
use App\Transaction;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletTest extends TestCase
{
    private $users = [
        'UserWallet1@UserWallet.com',
        'UserWallet2@UserWallet.com',
        'UserWallet3@UserWallet.com',
        'UserWallet4@UserWallet.com',
        'UserWallet5@UserWallet.com',
        'WalletControllerA@WalletController.com',
        'WalletControllerB@WalletController.com',
        'WalletController2A@WalletController.com',
        'WalletController2B@WalletController.com',
        'jane@kolabnow.com'
    ];

    public function setUp(): void
    {
        parent::setUp();

        foreach ($this->users as $user) {
            $this->deleteTestUser($user);
        }
    }

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $this->deleteTestUser($user);
        }

        Sku::select()->update(['fee' => 0]);

        parent::tearDown();
    }

    /**
     * Test that turning wallet balance from negative to positive
     * unsuspends the account
     */
    public function testBalancePositiveUnsuspend(): void
    {
        $user = $this->getTestUser('UserWallet1@UserWallet.com');
        $user->suspend();

        $wallet = $user->wallets()->first();
        $wallet->balance = -100;
        $wallet->save();

        $this->assertTrue($user->isSuspended());
        $this->assertNotNull($wallet->getSetting('balance_negative_since'));

        $wallet->balance = 100;
        $wallet->save();

        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertNull($wallet->getSetting('balance_negative_since'));

        // TODO: Test group account and unsuspending domain/members
    }

    /**
     * Test for Wallet::balanceLastsUntil()
     */
    public function testBalanceLastsUntil(): void
    {
        // Monthly cost of all entitlements: 999
        // 28 days: 35.68 per day
        // 31 days: 32.22 per day

        $user = $this->getTestUser('jane@kolabnow.com');
        $package = Package::where('title', 'kolab')->first();
        $user->assignPackage($package);
        $wallet = $user->wallets()->first();

        // User/entitlements created today, balance=0
        $until = $wallet->balanceLastsUntil();

        $this->assertSame(
            Carbon::now()->addMonthsWithoutOverflow(1)->toDateString(),
            $until->toDateString()
        );

        // User/entitlements created today, balance=-10 CHF
        $wallet->balance = -1000;
        $until = $wallet->balanceLastsUntil();

        $this->assertSame(null, $until);

        // User/entitlements created today, balance=-9,99 CHF (monthly cost)
        $wallet->balance = 999;
        $until = $wallet->balanceLastsUntil();

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $this->assertSame(
            Carbon::now()->addMonthsWithoutOverflow(1)->addDays($daysInLastMonth)->toDateString(),
            $until->toDateString()
        );

        // Old entitlements, 100% discount
        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subDays(40));
        $discount = \App\Discount::where('discount', 100)->first();
        $wallet->discount()->associate($discount);

        $until = $wallet->refresh()->balanceLastsUntil();

        $this->assertSame(null, $until);

        // User with no entitlements
        $wallet->discount()->dissociate($discount);
        $wallet->entitlements()->delete();

        $until = $wallet->refresh()->balanceLastsUntil();

        $this->assertSame(null, $until);
    }

    /**
     * Test for Wallet::costsPerDay()
     */
    public function testCostsPerDay(): void
    {
        // 999
        // 28 days: 35.68
        // 31 days: 32.22
        $user = $this->getTestUser('jane@kolabnow.com');

        $package = Package::where('title', 'kolab')->first();
        $mailbox = Sku::where('title', 'mailbox')->first();

        $user->assignPackage($package);

        $wallet = $user->wallets()->first();

        $costsPerDay = $wallet->costsPerDay();

        $this->assertTrue($costsPerDay < 35.68);
        $this->assertTrue($costsPerDay > 32.22);
    }

    /**
     * Verify a wallet is created, when a user is created.
     */
    public function testCreateUserCreatesWallet(): void
    {
        $user = $this->getTestUser('UserWallet1@UserWallet.com');

        $this->assertCount(1, $user->wallets);
    }

    /**
     * Verify a user can haz more wallets.
     */
    public function testAddWallet(): void
    {
        $user = $this->getTestUser('UserWallet2@UserWallet.com');

        $user->wallets()->save(
            new Wallet(['currency' => 'USD'])
        );

        $this->assertCount(2, $user->wallets);

        $user->wallets()->each(
            function ($wallet) {
                $this->assertEquals(0, $wallet->balance);
            }
        );
    }

    /**
     * Verify we can not delete a user wallet that holds balance.
     */
    public function testDeleteWalletWithCredit(): void
    {
        $user = $this->getTestUser('UserWallet3@UserWallet.com');

        $user->wallets()->each(
            function ($wallet) {
                $wallet->credit(100)->save();
            }
        );

        $user->wallets()->each(
            function ($wallet) {
                $this->assertFalse($wallet->delete());
            }
        );
    }

    /**
     * Verify we can not delete a wallet that is the last wallet.
     */
    public function testDeleteLastWallet(): void
    {
        $user = $this->getTestUser('UserWallet4@UserWallet.com');

        $this->assertCount(1, $user->wallets);

        $user->wallets()->each(
            function ($wallet) {
                $this->assertFalse($wallet->delete());
            }
        );
    }

    /**
     * Verify we can remove a wallet that is an additional wallet.
     */
    public function testDeleteAddtWallet(): void
    {
        $user = $this->getTestUser('UserWallet5@UserWallet.com');

        $user->wallets()->save(
            new Wallet(['currency' => 'USD'])
        );

        $user->wallets()->each(
            function ($wallet) {
                if ($wallet->currency == 'USD') {
                    $this->assertNotFalse($wallet->delete());
                }
            }
        );
    }

    /**
     * Verify a wallet can be assigned a controller.
     */
    public function testAddWalletController(): void
    {
        $userA = $this->getTestUser('WalletControllerA@WalletController.com');
        $userB = $this->getTestUser('WalletControllerB@WalletController.com');

        $userA->wallets()->each(
            function ($wallet) use ($userB) {
                $wallet->addController($userB);
            }
        );

        $this->assertCount(1, $userB->accounts);

        $aWallet = $userA->wallets()->first();
        $bAccount = $userB->accounts()->first();

        $this->assertTrue($bAccount->id === $aWallet->id);
    }

    /**
     * Verify controllers can also be removed from wallets.
     */
    public function testRemoveWalletController(): void
    {
        $userA = $this->getTestUser('WalletController2A@WalletController.com');
        $userB = $this->getTestUser('WalletController2B@WalletController.com');

        $userA->wallets()->each(
            function ($wallet) use ($userB) {
                $wallet->addController($userB);
            }
        );

        $userB->refresh();

        $userB->accounts()->each(
            function ($wallet) use ($userB) {
                $wallet->removeController($userB);
            }
        );

        $this->assertCount(0, $userB->accounts);
    }

    /**
     * Test for charging and removing entitlements (including tenant commission calculations)
     */
    public function testChargeAndDeleteEntitlements(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();
        $discount = \App\Discount::where('discount', 30)->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        // Add 40% fee to all SKUs
        Sku::select()->update(['fee' => DB::raw("`cost` * 0.4")]);

        $package = Package::where('title', 'kolab')->first();
        $storage = Sku::where('title', 'storage')->first();
        $user->assignPackage($package);
        $user->assignSku($storage, 2);
        $user->refresh();

        // Reset reseller's wallet balance and transactions
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        Transaction::where('object_id', $reseller_wallet->id)->where('object_type', \App\Wallet::class)->delete();

        // ------------------------------------
        // Test normal charging of entitlements
        // ------------------------------------

        // Backdate and chanrge entitlements, we're expecting one month to be charged
        // Set fake NOW date to make simpler asserting results that depend on number of days in current/last month
        Carbon::setTestNow(Carbon::create(2021, 5, 21, 12));
        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements, $backdate);
        $charge = $wallet->chargeEntitlements();
        $wallet->refresh();
        $reseller_wallet->refresh();

        // 388 + 310 + 17 + 17 = 732
        $this->assertSame(-732, $wallet->balance);
        // 388 - 555 x 40% + 310 - 444 x 40% + 34 - 50 x 40% = 312
        $this->assertSame(312, $reseller_wallet->balance);

        $transactions = Transaction::where('object_id', $wallet->id)
            ->where('object_type', \App\Wallet::class)->get();
        $reseller_transactions = Transaction::where('object_id', $reseller_wallet->id)
            ->where('object_type', \App\Wallet::class)->get();

        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(312, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);

        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-732, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        // TODO: Test entitlement transaction records

        // -----------------------------------
        // Test charging on entitlement delete
        // -----------------------------------

        $transactions = Transaction::where('object_id', $wallet->id)
            ->where('object_type', \App\Wallet::class)->delete();
        $reseller_transactions = Transaction::where('object_id', $reseller_wallet->id)
            ->where('object_type', \App\Wallet::class)->delete();

        $user->removeSku($storage, 2);

        // we expect the wallet to have been charged for 19 days of use of
        // 2 deleted storage entitlements
        $wallet->refresh();
        $reseller_wallet->refresh();

        // 2 x round(25 / 31 * 19 * 0.7) = 22
        $this->assertSame(-(732 + 22), $wallet->balance);
        // 22 - 2 x round(25 * 0.4 / 31 * 19) = 10
        $this->assertSame(312 + 10, $reseller_wallet->balance);

        $transactions = Transaction::where('object_id', $wallet->id)
            ->where('object_type', \App\Wallet::class)->get();
        $reseller_transactions = Transaction::where('object_id', $reseller_wallet->id)
            ->where('object_type', \App\Wallet::class)->get();

        $this->assertCount(2, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(5, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);
        $trans = $reseller_transactions[1];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(5, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);

        $this->assertCount(2, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-11, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);
        $trans = $transactions[1];
        $this->assertSame('', $trans->description);
        $this->assertSame(-11, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        // TODO: Test entitlement transaction records
    }
}
