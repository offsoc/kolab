<?php

namespace Tests\Feature;

use App\User;
use App\Wallet;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserWalletTest extends TestCase
{
    /**
        Verify a wallet is created, when a user is created.

        @return void
     */
    public function testCreateUserCreatesWallet()
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet1@UserWallet.com'
            ]
        );

        $this->assertTrue($user->wallets()->count() == 1);
    }

    /**
        Verify a user can haz more wallets.

        @return void
     */
    public function testAddWallet()
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
        Verify we can not delete a user wallet that holds balance.

        @return void
     */
    public function testDeleteWalletWithCredit()
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
        Verify we can not delete a wallet that is the last wallet.

        @return void
     */
    public function testDeleteLastWallet()
    {
        $user = User::firstOrCreate(
            [
                'email' => 'UserWallet4@UserWallet.com'
            ]
        );

        $user->wallets()->each(
            function ($wallet) {
                $this->assertFalse($wallet->delete());
            }
        );
    }

    /**
        Verify we can remove a wallet that is an additional wallet.

        @return void
     */
    public function testDeleteAddtWallet()
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
}
