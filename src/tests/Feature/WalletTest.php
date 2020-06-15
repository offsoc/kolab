<?php

namespace Tests\Feature;

use App\Package;
use App\User;
use App\Sku;
use App\Wallet;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        parent::tearDown();
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

        $this->assertSame(Carbon::now()->toDateString(), $until->toDateString());

        // User/entitlements created today, balance=-10 CHF
        $wallet->balance = -1000;
        $until = $wallet->balanceLastsUntil();

        $this->assertSame(null, $until);

        // User/entitlements created today, balance=-9,99 CHF (monthly cost)
        $wallet->balance = 999;
        $until = $wallet->balanceLastsUntil();

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $this->assertSame(
            Carbon::now()->addDays($daysInLastMonth)->toDateString(),
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
}
