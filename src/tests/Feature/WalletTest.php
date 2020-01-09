<?php

namespace Tests\Feature;

use App\User;
use App\Wallet;
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
        'UserWallet5@UserWallet.com'
    ];

    public function setUp(): void
    {
        parent::setUp();

        foreach ($this->users as $user) {
            $_user = User::firstOrCreate(['email' => $user]);
            $_user->delete();
        }
    }

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $_user = User::firstOrCreate(['email' => $user]);
            $_user->delete();
        }
    }

    /**
     * Verify a wallet is created, when a user is created.
     */
    public function testCreateUserCreatesWallet(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet1@UserWallet.com'
            ]
        );

        $this->assertTrue($user->wallets()->count() == 1);
    }

    /**
     * Verify a user can haz more wallets.
     */
    public function testAddWallet(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet2@UserWallet.com'
            ]
        );

        $user->wallets()->save(
            new Wallet(['currency' => 'USD'])
        );

        $this->assertTrue($user->wallets()->count() >= 2);

        $user->wallets()->each(
            function ($wallet) {
                $this->assertTrue($wallet->balance === 0.00);
            }
        );
    }

    /**
     * Verify we can not delete a user wallet that holds balance.
     */
    public function testDeleteWalletWithCredit(): void
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet3@UserWallet.com'
            ]
        );

        $user->wallets()->each(
            function ($wallet) {
                $wallet->credit(1.00)->save();
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
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet4@UserWallet.com'
            ]
        );

        $this->assertTrue($user->wallets()->count() == 1);

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
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet5@UserWallet.com'
            ]
        );

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
        $userA = User::firstOrCreate(
            [
                'email' => 'WalletControllerA@WalletController.com'
            ]
        );

        $userA->wallets()->each(
            function ($wallet) {
                $userB = User::firstOrCreate(
                    [
                        'email' => 'WalletControllerB@WalletController.com'
                    ]
                );

                $wallet->addController($userB);
            }
        );

        $userB = User::firstOrCreate(
            [
                'email' => 'WalletControllerB@WalletController.com'
            ]
        );

        $this->assertTrue($userB->accounts()->count() == 1);

        $aWallet = $userA->wallets()->get();
        $bAccount = $userB->accounts()->get();

        $this->assertTrue($bAccount[0]->id === $aWallet[0]->id);
    }

    /**
     * Verify controllers can also be removed from wallets.
     */
    public function testRemoveWalletController(): void
    {
        $userA = User::firstOrCreate(
            [
                'email' => 'WalletController2A@WalletController.com'
            ]
        );

        $userA->wallets()->each(
            function ($wallet) {
                $userB = User::firstOrCreate(
                    [
                        'email' => 'WalletController2B@WalletController.com'
                    ]
                );

                $wallet->addController($userB);
            }
        );

        $userB = User::firstOrCreate(
            [
                'email' => 'WalletController2B@WalletController.com'
            ]
        );

        $userB->accounts()->each(
            function ($wallet) {
                $userB = User::firstOrCreate(
                    [
                        'email' => 'WalletController2B@WalletController.com'
                    ]
                );

                $wallet->removeController($userB);
            }
        );

        $this->assertTrue($userB->accounts()->count() == 0);
    }
}
