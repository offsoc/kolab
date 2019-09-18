<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletControllerTest extends TestCase
{
    /**
        Verify a wallet can be assigned a controller.

        @return void
     */
    public function testAddWalletController()
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
        Verify controllers can also be removed from wallets.

        @return void
     */
    public function testRemoveWalletController()
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
