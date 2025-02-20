<?php

namespace Tests\Feature;

use App\Discount;
use App\Entitlement;
use App\Payment;
use App\Package;
use App\Plan;
use App\User;
use App\Sku;
use App\Transaction;
use App\Wallet;
use App\VatRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletTest extends TestCase
{
    private $users = [
        'UserWallet1@UserWallet.com',
        'UserWallet2@UserWallet.com',
        'UserWallet3@UserWallet.com',
        'jane@kolabnow.com'
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromDate(2022, 02, 02));
        foreach ($this->users as $user) {
            $this->deleteTestUser($user);
        }

        Sku::select()->update(['fee' => 0]);
        Payment::query()->delete();
        VatRate::query()->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $this->deleteTestUser($user);
        }

        Sku::select()->update(['fee' => 0]);
        Payment::query()->delete();
        VatRate::query()->delete();
        Plan::withEnvTenantContext()->where('title', 'individual')->update(['months' => 1]);

        parent::tearDown();
    }

    /**
     * Test that turning wallet balance from negative to positive
     * unsuspends and undegrades the account
     */
    public function testBalanceTurnsPositive(): void
    {
        Queue::fake();

        $user = $this->getTestUser('jane@kolabnow.com');
        $user->suspend();
        $user->degrade();

        $wallet = $user->wallets()->first();
        $wallet->balance = -100;
        $wallet->save();

        $this->assertTrue($user->isSuspended());
        $this->assertTrue($user->isDegraded());
        $this->assertNotNull($wallet->getSetting('balance_negative_since'));

        $wallet->balance = 100;
        $wallet->save();

        $user->refresh();

        $this->assertFalse($user->isSuspended());
        $this->assertFalse($user->isDegraded());
        $this->assertNull($wallet->getSetting('balance_negative_since'));

        // Test un-restricting users on balance change
        $owner = $this->getTestUser('UserWallet1@UserWallet.com');
        $user1 = $this->getTestUser('UserWallet2@UserWallet.com');
        $user2 = $this->getTestUser('UserWallet3@UserWallet.com');
        $package = Package::withEnvTenantContext()->where('title', 'lite')->first();
        $owner->assignPackage($package, $user1);
        $owner->assignPackage($package, $user2);
        $wallet = $owner->wallets()->first();

        $owner->restrict();
        $user1->restrict();
        $user2->restrict();

        $this->assertTrue($owner->isRestricted());
        $this->assertTrue($user1->isRestricted());
        $this->assertTrue($user2->isRestricted());

        $this->fakeQueueReset();

        $wallet->balance = 100;
        $wallet->save();

        $this->assertFalse($owner->fresh()->isRestricted());
        $this->assertFalse($user1->fresh()->isRestricted());
        $this->assertFalse($user2->fresh()->isRestricted());

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 3);

        // TODO: Test group account and unsuspending domain/members/groups
    }

    /**
     * Test for Wallet::balanceLastsUntil()
     */
    public function testBalanceLastsUntil(): void
    {
        // Monthly cost of all entitlements: 990
        // 28 days: 35.36 per day
        // 31 days: 31.93 per day

        $user = $this->getTestUser('jane@kolabnow.com');
        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $user->assignPlan($plan);
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
        $wallet->balance = 990;
        $until = $wallet->balanceLastsUntil();

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $delta = Carbon::now()->addMonthsWithoutOverflow(1)->addDays($daysInLastMonth)->diff($until)->days;

        $this->assertTrue($delta <= 1);
        $this->assertTrue($delta >= -1);

        // Old entitlements, 100% discount
        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subDays(40));
        $discount = \App\Discount::withEnvTenantContext()->where('discount', 100)->first();
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
     * Basic wallet features
     */
    public function testWallet(): void
    {
        // Verify a wallet is created, when a user is created.
        $user = $this->getTestUser('UserWallet1@UserWallet.com');

        $this->assertCount(1, $user->wallets);
        $this->assertSame(\config('app.currency'), $user->wallets[0]->currency);
        $this->assertSame(0, $user->wallets[0]->balance);

        // Verify a user can haz more wallets.
        $user->wallets()->save(new Wallet(['currency' => 'USD']));
        $user->refresh();

        $this->assertCount(2, $user->wallets);

        $user->wallets()->each(
            function ($wallet) {
                $this->assertEquals(0, $wallet->balance);
            }
        );

        // For now all wallets use system currency
        $this->assertFalse($user->wallets()->where('currency', 'USD')->exists());

        // Verify we can not delete a user wallet that holds balance.
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

        $user->wallets()->update(['balance' => 0]);
        $user->refresh();

        // Verify we can remove a wallet that is an additional wallet.
        $user->wallets()->first()->delete();
        $user->refresh();

        $this->assertCount(1, $user->wallets);

        // Verify we can not delete a wallet that is the last wallet.
        $this->assertFalse($user->wallets[0]->delete());
    }

    /**
     * Verify a wallet can be assigned a controller.
     */
    public function testAddController(): void
    {
        $userA = $this->getTestUser('UserWallet1@UserWallet.com');
        $userB = $this->getTestUser('UserWallet2@UserWallet.com');

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
     * Test Wallet::expectedCharges()
     */
    public function testExpectedCharges(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $package = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $user->assignPackage($package);

        $wallet = $user->wallets->first();

        // Verify the last day before the end of a full month's trial.
        $this->backdateEntitlements(
            $wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->addDays(1)
        );

        $this->assertEquals(0, $wallet->expectedCharges());

        // Verify the exact end of the month's trial.
        $this->backdateEntitlements(
            $wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)
        );

        $this->assertEquals(990, $wallet->expectedCharges());

        // Verify that over-running the trial by a single day causes charges to be incurred.
        $this->backdateEntitlements(
            $wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(990, $wallet->expectedCharges());

        // Verify additional storage configuration entitlement created 'early' does incur additional
        // charges to the wallet.
        $this->backdateEntitlements(
            $wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(990, $wallet->expectedCharges());

        $sku = Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $entitlement = Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => $sku->cost,
                'entitleable_id' => $user->id,
                'entitleable_type' => \App\User::class
        ]);

        $this->backdateEntitlements(
            [$entitlement],
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(1015, $wallet->expectedCharges());

        $entitlement->forceDelete();
        $wallet->refresh();

        // Verify additional storage configuration entitlement created 'late' does not incur additional
        // charges to the wallet.
        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(990, $wallet->expectedCharges());

        $entitlement = \App\Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => $sku->cost,
                'entitleable_id' => $user->id,
                'entitleable_type' => \App\User::class
        ]);

        $this->backdateEntitlements([$entitlement], Carbon::now()->subDays(14));

        $this->assertEquals(990, $wallet->expectedCharges());

        $entitlement->forceDelete();
        $wallet->refresh();

        // Test fifth week
        $targetDateA = Carbon::now()->subWeeks(5);
        $targetDateB = $targetDateA->copy()->addMonthsWithoutOverflow(1);

        $this->backdateEntitlements($wallet->entitlements, $targetDateA);

        $this->assertEquals(990, $wallet->expectedCharges());

        $entitlement->forceDelete();
        $wallet->refresh();

        // Test second month
        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(2));

        $this->assertCount(7, $wallet->entitlements);

        $this->assertEquals(1980, $wallet->expectedCharges());

        $entitlement = \App\Entitlement::create([
                'entitleable_id' => $user->id,
                'entitleable_type' => \App\User::class,
                'cost' => $sku->cost,
                'sku_id' => $sku->id,
                'wallet_id' => $wallet->id
        ]);

        $this->backdateEntitlements([$entitlement], Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(2005, $wallet->expectedCharges());

        $entitlement->forceDelete();
        $wallet->refresh();

        // Test cost calculation with a wallet discount
        $discount = Discount::withEnvTenantContext()->where('code', 'TEST')->first();

        $wallet->discount()->associate($discount);

        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(891, $wallet->expectedCharges());
    }

    /**
     * Test Wallet::getMinMandateAmount()
     */
    public function testGetMinMandateAmount(): void
    {
        $user = $this->getTestUser('UserWallet1@UserWallet.com');
        $user->setSetting('plan_id', null);
        $wallet = $user->wallets()->first();

        // No plan assigned
        $this->assertSame(Payment::MIN_AMOUNT, $wallet->getMinMandateAmount());

        // Plan assigned
        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $plan->months = 12;
        $plan->save();

        $user->setSetting('plan_id', $plan->id);

        $this->assertSame(990 * 12, $wallet->getMinMandateAmount());

        // Plan and discount
        $discount = Discount::where('discount', 30)->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        $this->assertSame((int) (990 * 12 * 0.70), $wallet->getMinMandateAmount());
    }

    /**
     * Test Wallet::isController()
     */
    public function testIsController(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $wallet = $jack->wallet();

        $this->assertTrue($wallet->isController($john));
        $this->assertTrue($wallet->isController($ned));
        $this->assertFalse($wallet->isController($jack));
    }

    /**
     * Verify controllers can also be removed from wallets.
     */
    public function testRemoveController(): void
    {
        $userA = $this->getTestUser('UserWallet1@UserWallet.com');
        $userB = $this->getTestUser('UserWallet2@UserWallet.com');

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
     * Test for charging entitlements (including tenant commission calculations)
     */
    public function testChargeEntitlements(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $discount = \App\Discount::withEnvTenantContext()->where('discount', 30)->first();
        $wallet = $user->wallets()->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        // Add 40% fee to all SKUs
        Sku::select()->update(['fee' => DB::raw("`cost` * 0.4")]);

        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $storage = Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $mailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $groupware = Sku::withEnvTenantContext()->where('title', 'groupware')->first();
        $user->assignPlan($plan);
        $user->assignSku($storage, 5);
        $user->setSetting('plan_id', null); // disable plan and trial

        // Set fake NOW date to make simpler asserting results that depend on number of days in current/last month
        Carbon::setTestNow(Carbon::create(2021, 5, 21, 12));

        // Add extra user with some deleted entitlements to make sure it does not interfere
        $otherUser = $this->getTestUser('UserWallet1@UserWallet.com');
        $otherUser->assignPlan($plan);
        $otherUser->assignSku($storage, 5);
        $this->backdateEntitlements($otherUser->entitlements, Carbon::now()->subWeeks(7));
        $otherUser->removeSku($storage, 2);
        $otherUser->entitlements()->withTrashed()->whereNotNull('deleted_at')
            ->update(['updated_at' => Carbon::now()->subWeeks(8)]);

        // Reset reseller's wallet balance and transactions
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        // ------------------------------------------------
        // Test skipping entitlements before a month passed
        // ------------------------------------------------

        $backdate = Carbon::now()->subWeeks(3);
        $this->backdateEntitlements($user->entitlements, $backdate);

        // we expect no charges
        $this->assertSame(0, $wallet->chargeEntitlements());
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $reseller_wallet->balance);
        $this->assertSame(0, $wallet->transactions()->count());
        $this->assertSame(12, $user->entitlements()->where('updated_at', $backdate)->count());

        // ------------------------------------
        // Test normal charging of entitlements
        // ------------------------------------

        // Backdate and charge entitlements, we're expecting one month to be charged
        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements, $backdate);

        // Test with $apply=false argument
        $charge = $wallet->chargeEntitlements(false);

        $this->assertSame(778, $charge);
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $wallet->transactions()->count());

        $charge = $wallet->chargeEntitlements();
        $wallet->refresh();
        $reseller_wallet->refresh();

        // User discount is 30%
        // Expected: groupware: floor(490 * 70%) + mailbox: floor(500 * 70%) + storage: 5 * floor(25 * 70%) = 778
        $this->assertSame(778, $charge);
        $this->assertSame(-778, $wallet->balance);
        // Reseller fee is 40%
        // Expected: 778 - groupware: floor(490 * 40%) - mailbox: floor(500 * 40%) - storage: 5 * floor(25 * 40%) = 332
        $this->assertSame(332, $reseller_wallet->balance);

        $transactions = $wallet->transactions()->get();
        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-778, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        $reseller_transactions = $reseller_wallet->transactions()->get();
        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(332, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);

        // Assert all entitlements' updated_at timestamp
        $date = $backdate->addMonthsWithoutOverflow(1);
        $this->assertCount(12, $wallet->entitlements()->where('updated_at', $date)->get());

        // Assert per-entitlement transactions
        $entitlement_transactions = Transaction::where('transaction_id', $transactions[0]->id)
            ->where('type', Transaction::ENTITLEMENT_BILLED)
            ->get();
        $this->assertSame(7, $entitlement_transactions->count());
        $this->assertSame(778, $entitlement_transactions->sum('amount'));
        $groupware_entitlement = $user->entitlements->where('sku_id', '===', $groupware->id)->first();
        $mailbox_entitlement = $user->entitlements->where('sku_id', '===', $mailbox->id)->first();
        $this->assertSame(1, $entitlement_transactions->where('object_id', $groupware_entitlement->id)->count());
        $this->assertSame(1, $entitlement_transactions->where('object_id', $mailbox_entitlement->id)->count());
        $excludes = [$mailbox_entitlement->id, $groupware_entitlement->id];
        $this->assertSame(5, $entitlement_transactions->whereNotIn('object_id', $excludes)->count());

        // -----------------------------------
        // Test charging deleted entitlements
        // -----------------------------------

        $wallet->balance = 0;
        $wallet->save();
        $wallet->transactions()->delete();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        $user->removeSku($storage, 2);

        // we expect the wallet to have been charged for 19 days of use of 2 deleted storage entitlements
        $charge = $wallet->chargeEntitlements();
        $wallet->refresh();
        $reseller_wallet->refresh();

        // 2 * floor(25 / 31 * 70% * 19) = 20
        $this->assertSame(20, $charge);
        $this->assertSame(-20, $wallet->balance);
        // 20 - 2 * floor(25 / 31 * 40% * 19) = 8
        $this->assertSame(8, $reseller_wallet->balance);

        $transactions = $wallet->transactions()->get();
        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-20, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        $reseller_transactions = $reseller_wallet->transactions()->get();
        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(8, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);

        // Assert per-entitlement transactions
        $entitlement_transactions = Transaction::where('transaction_id', $transactions[0]->id)
            ->where('type', Transaction::ENTITLEMENT_BILLED)
            ->get();
        $storage_entitlements = $user->entitlements->where('sku_id', $storage->id)->where('cost', '>', 0)->pluck('id');
        $this->assertSame(2, $entitlement_transactions->count());
        $this->assertSame(20, $entitlement_transactions->sum('amount'));
        $this->assertSame(2, $entitlement_transactions->whereIn('object_id', $storage_entitlements)->count());

        // --------------------------------------------------
        // Test skipping deleted entitlements already charged
        // --------------------------------------------------

        $wallet->balance = 0;
        $wallet->save();
        $wallet->transactions()->delete();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        // we expect no charges
        $this->assertSame(0, $wallet->chargeEntitlements());
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $wallet->transactions()->count());
        $this->assertSame(0, $reseller_wallet->fresh()->balance);

        // ---------------------------------------------------------
        // Test (not) charging entitlements deleted before 14 days
        // ---------------------------------------------------------

        $backdate = Carbon::now()->subDays(13);

        $ent = $user->entitlements->where('sku_id', $groupware->id)->first();
        Entitlement::where('id', $ent->id)->update([
                'created_at' => $backdate,
                'updated_at' => $backdate,
                'deleted_at' => Carbon::now(),
        ]);

        // we expect no charges
        $this->assertSame(0, $wallet->chargeEntitlements());
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $wallet->transactions()->count());
        $this->assertSame(0, $reseller_wallet->fresh()->balance);
        // expect update of updated_at timestamp
        $this->assertSame(Carbon::now()->toDateTimeString(), $ent->fresh()->updated_at->toDateTimeString());

        // -------------------------------------------------------
        // Test charging a degraded account
        // Test both deleted and non-deleted in the same operation
        // -------------------------------------------------------

        // At this point user has: mailbox + 8 x storage
        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements->fresh(), $backdate);

        $user->status |= User::STATUS_DEGRADED;
        $user->saveQuietly();

        $wallet->refresh();
        $wallet->balance = 0;
        $wallet->save();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        Transaction::truncate();

        $charge = $wallet->chargeEntitlements();
        $reseller_wallet->refresh();

        // User would be charged if not degraded: mailbox: floor(500 * 70%) + storage: 3 * floor(25 * 70%) = 401
        $this->assertSame(0, $charge);
        $this->assertSame(0, $wallet->balance);
        // Expected: 0 - mailbox: floor(500 * 40%) - storage: 3 * floor(25 * 40%) = -230
        $this->assertSame(-230, $reseller_wallet->balance);

        // Assert all entitlements' updated_at timestamp
        $date = $backdate->addMonthsWithoutOverflow(1);
        $this->assertSame(9, $wallet->entitlements()->where('updated_at', $date)->count());
        // There should be only one transaction at this point (for the reseller wallet)
        $this->assertSame(1, Transaction::count());
    }

    /**
     * Test for charging entitlements when in trial
     */
    public function testChargeEntitlementsTrial(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $wallet = $user->wallets()->first();

        // Add 40% fee to all SKUs
        Sku::select()->update(['fee' => DB::raw("`cost` * 0.4")]);

        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $storage = Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $user->assignPlan($plan);
        $user->assignSku($storage, 5);

        // Reset reseller's wallet balance and transactions
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        // Set fake NOW date to make simpler asserting results that depend on number of days in current/last month
        Carbon::setTestNow(Carbon::create(2021, 5, 21, 12));

        // ------------------------------------
        // Test normal charging of entitlements
        // ------------------------------------

        // Backdate and charge entitlements, we're expecting one month to be charged
        $backdate = Carbon::now()->subWeeks(7); // 2021-04-02
        $this->backdateEntitlements($user->entitlements, $backdate, $backdate);
        $charge = $wallet->chargeEntitlements();
        $reseller_wallet->refresh();

        // Expected: storage: 5 * 25 = 125 (the rest is free in trial)
        $this->assertSame($balance = -125, $wallet->balance);
        $this->assertSame(-$balance, $charge);

        // Reseller fee is 40%
        // Expected: 125 - 5 * floor(25 * 40%) = 75
        $this->assertSame($reseller_balance = 75, $reseller_wallet->balance);

        // Assert wallet transaction
        $transactions = $wallet->transactions()->get();
        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame($balance, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        // Assert entitlement transactions
        $etransactions = Transaction::where('transaction_id', $trans->id)->get();
        $this->assertCount(5, $etransactions);
        $trans = $etransactions[0];
        $this->assertSame(null, $trans->description);
        $this->assertSame(25, $trans->amount);
        $this->assertSame(Transaction::ENTITLEMENT_BILLED, $trans->type);

        // Assert all entitlements' updated_at timestamp
        $date = $backdate->addMonthsWithoutOverflow(1);
        $this->assertCount(12, $wallet->entitlements()->where('updated_at', $date)->get());

        // Run again, expect no changes
        $charge = $wallet->chargeEntitlements();
        $wallet->refresh();

        $this->assertSame(0, $charge);
        $this->assertSame($balance, $wallet->balance);
        $this->assertCount(1, $wallet->transactions()->get());
        $this->assertCount(12, $wallet->entitlements()->where('updated_at', $date)->get());

        // -----------------------------------
        // Test charging deleted entitlements
        // -----------------------------------

        $wallet->balance = 0;
        $wallet->save();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        Transaction::truncate();

        $user->removeSku($storage, 2);

        $charge = $wallet->chargeEntitlements();

        $wallet->refresh();
        $reseller_wallet->refresh();

        // we expect the wallet to have been charged for 19 days of use of
        // 2 deleted storage entitlements: 2 * floor(25 / 31 * 19) = 30
        $this->assertSame(-30, $wallet->balance);
        $this->assertSame(30, $charge);

        // Reseller fee is 40%
        // Expected: 30 - 2 * floor(25 / 31 * 40% * 19) = 18
        $this->assertSame(18, $reseller_wallet->balance);

        // Assert wallet transactions
        $transactions = $wallet->transactions()->get();
        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-30, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        // Assert entitlement transactions
        $etransactions = Transaction::where('transaction_id', $trans->id)->get();
        $this->assertCount(2, $etransactions);
        $trans = $etransactions[0];
        $this->assertSame(null, $trans->description);
        $this->assertSame(15, $trans->amount);
        $this->assertSame(Transaction::ENTITLEMENT_BILLED, $trans->type);

        // Assert the deleted entitlements' updated_at timestamp was bumped
        $this->assertSame(2, $wallet->entitlements()->withTrashed()->whereColumn('updated_at', 'deleted_at')->count());

        // TODO: Test a case when trial ends after the entitlement deletion date
    }

    /**
     * Tests for award/penalty/chargeback/refund/credit/debit methods
     */
    public function testBalanceChange(): void
    {
        $user = $this->getTestUser('UserWallet1@UserWallet.com');
        $wallet = $user->wallets()->first();

        // Test award
        $this->assertSame($wallet->id, $wallet->award(100, 'test')->id);
        $this->assertSame(100, $wallet->balance);
        $this->assertSame(100, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_AWARD, $transaction->type);
        $this->assertSame('test', $transaction->description);

        $wallet->transactions()->delete();

        // Test penalty
        $this->assertSame($wallet->id, $wallet->penalty(100, 'test')->id);
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(-100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_PENALTY, $transaction->type);
        $this->assertSame('test', $transaction->description);

        $wallet->transactions()->delete();
        $wallet->balance = 0;
        $wallet->save();

        // Test chargeback
        $this->assertSame($wallet->id, $wallet->chargeback(100, 'test')->id);
        $this->assertSame(-100, $wallet->balance);
        $this->assertSame(-100, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(-100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_CHARGEBACK, $transaction->type);
        $this->assertSame('test', $transaction->description);

        $wallet->transactions()->delete();
        $wallet->balance = 0;
        $wallet->save();

        // Test refund
        $this->assertSame($wallet->id, $wallet->refund(100, 'test')->id);
        $this->assertSame(-100, $wallet->balance);
        $this->assertSame(-100, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(-100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_REFUND, $transaction->type);
        $this->assertSame('test', $transaction->description);

        $wallet->transactions()->delete();
        $wallet->balance = 0;
        $wallet->save();

        // Test credit
        $this->assertSame($wallet->id, $wallet->credit(100, 'test')->id);
        $this->assertSame(100, $wallet->balance);
        $this->assertSame(100, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $transaction->type);
        $this->assertSame('test', $transaction->description);

        $wallet->transactions()->delete();
        $wallet->balance = 0;
        $wallet->save();

        // Test debit
        $this->assertSame($wallet->id, $wallet->debit(100, 'test')->id);
        $this->assertSame(-100, $wallet->balance);
        $this->assertSame(-100, $wallet->fresh()->balance);
        $transaction = $wallet->transactions()->first();
        $this->assertSame(-100, $transaction->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $transaction->type);
        $this->assertSame('test', $transaction->description);
    }

    /**
     * Tests for topUp()
     */
    public function testTopUp(): void
    {
        // TODO: Tests from tests/Feature/Controller/Payments*Test.php that invoke
        // Wallet::topUp() should be moved here
        $this->markTestIncomplete();
    }

    /**
     * Tests for updateEntitlements()
     */
    public function testUpdateEntitlements(): void
    {
        $user = $this->getTestUser('jane@kolabnow.com');
        $discount = \App\Discount::withEnvTenantContext()->where('discount', 30)->first();
        $wallet = $user->wallets()->first();
        $wallet->discount()->associate($discount);
        $wallet->save();

        // Add 40% fee to all SKUs
        Sku::select()->update(['fee' => DB::raw("`cost` * 0.4")]);

        $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
        $storage = Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $mailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $groupware = Sku::withEnvTenantContext()->where('title', 'groupware')->first();
        $user->assignPlan($plan);
        $user->setSetting('plan_id', null); // disable plan and trial

        // Reset reseller's wallet balance and transactions
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        // Set fake NOW date to make simpler asserting results that depend on number of days in current/last month
        Carbon::setTestNow(Carbon::create(2021, 5, 21, 12));
        $now = Carbon::now();

        // Backdate and charge entitlements
        $backdate = Carbon::now()->subWeeks(3)->setHour(10);
        $this->backdateEntitlements($user->entitlements, $backdate);

        // ---------------------------------------
        // Update entitlements with no cost charge
        // ---------------------------------------

        // Test with $withCost=false argument
        $charge = $wallet->updateEntitlements(false);
        $wallet->refresh();
        $reseller_wallet->refresh();

        $this->assertSame(0, $charge);
        $this->assertSame(0, $wallet->balance);
        $this->assertSame(0, $wallet->transactions()->count());
        // Expected: 0 - groupware: floor(490 / 31 * 21 * 40%) - mailbox: floor(500 / 31 * 21 * 40%) = -267
        $this->assertSame(-267, $reseller_wallet->balance);

        // Assert all entitlements' updated_at timestamp
        $date = $now->copy()->setTimeFrom($backdate);
        $this->assertCount(7, $wallet->entitlements()->where('updated_at', $date)->get());

        $reseller_transactions = $reseller_wallet->transactions()->get();
        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(-267, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        // ------------------------------------
        // Update entitlements with cost charge
        // ------------------------------------

        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        $reseller_wallet->transactions()->delete();

        $this->backdateEntitlements($user->entitlements, $backdate);

        $charge = $wallet->updateEntitlements();
        $wallet->refresh();
        $reseller_wallet->refresh();

        // User discount is 30%
        // Expected: groupware: floor(490 / 31 * 21 * 70%) + mailbox: floor(500 / 31 * 21 * 70%) = 469
        $this->assertSame(469, $charge);
        $this->assertSame(-469, $wallet->balance);
        // Reseller fee is 40%
        // Expected: 469 - groupware: floor(490 / 31 * 21 * 40%) - mailbox: floor(500 / 31 * 21 * 40%) = 202
        $this->assertSame(202, $reseller_wallet->balance);

        $transactions = $wallet->transactions()->get();
        $this->assertCount(1, $transactions);
        $trans = $transactions[0];
        $this->assertSame('', $trans->description);
        $this->assertSame(-469, $trans->amount);
        $this->assertSame(Transaction::WALLET_DEBIT, $trans->type);

        $reseller_transactions = $reseller_wallet->transactions()->get();
        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Charged user jane@kolabnow.com", $trans->description);
        $this->assertSame(202, $trans->amount);
        $this->assertSame(Transaction::WALLET_CREDIT, $trans->type);

        // Assert all entitlements' updated_at timestamp
        $date = $now->copy()->setTimeFrom($backdate);
        $this->assertCount(7, $wallet->entitlements()->where('updated_at', $date)->get());

        // Assert per-entitlement transactions
        $groupware_entitlement = $user->entitlements->where('sku_id', '===', $groupware->id)->first();
        $mailbox_entitlement = $user->entitlements->where('sku_id', '===', $mailbox->id)->first();
        $entitlement_transactions = Transaction::where('transaction_id', $transactions[0]->id)
            ->where('type', Transaction::ENTITLEMENT_BILLED)
            ->get();
        $this->assertSame(2, $entitlement_transactions->count());
        $this->assertSame(469, $entitlement_transactions->sum('amount'));
        $this->assertSame(1, $entitlement_transactions->where('object_id', $groupware_entitlement->id)->count());
        $this->assertSame(1, $entitlement_transactions->where('object_id', $mailbox_entitlement->id)->count());
    }

    /**
     * Tests for vatRate()
     */
    public function testVatRate(): void
    {
        $rate1 = VatRate::create([
                'start' => now()->subDay(),
                'country' => 'US',
                'rate' => 7.5,
        ]);
        $rate2 = VatRate::create([
                'start' => now()->subDay(),
                'country' => 'DE',
                'rate' => 10.0,
        ]);

        $user = $this->getTestUser('UserWallet1@UserWallet.com');
        $wallet = $user->wallets()->first();

        $user->setSetting('country', null);
        $this->assertSame(null, $wallet->vatRate());

        $user->setSetting('country', 'PL');
        $this->assertSame(null, $wallet->vatRate());

        $user->setSetting('country', 'US');
        $this->assertSame($rate1->id, $wallet->vatRate()->id); // @phpstan-ignore-line

        $user->setSetting('country', 'DE');
        $this->assertSame($rate2->id, $wallet->vatRate()->id); // @phpstan-ignore-line

        // Test $start argument
        $rate3 = VatRate::create([
                'start' => now()->subYear(),
                'country' => 'DE',
                'rate' => 5.0,
        ]);

        $this->assertSame($rate2->id, $wallet->vatRate()->id); // @phpstan-ignore-line
        $this->assertSame($rate3->id, $wallet->vatRate(now()->subMonth())->id);
        $this->assertSame(null, $wallet->vatRate(now()->subYears(2)));
    }
}
