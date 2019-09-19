<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAccountTest extends TestCase
{
    /**
        Verify a wallet assigned a controller is among the accounts of the assignee.

        @return void
     */
    public function testListUserAccounts()
    {
        $userA = User::firstOrCreate(
            [
                'email' => 'UserAccountA@UserAccount.com'
            ]
        );

        $this->assertTrue($userA->wallets()->count() == 1);

        $userA->wallets()->each(
            function ($wallet) {
                $userB = User::firstOrCreate(
                    [
                        'email' => 'UserAccountB@UserAccount.com'
                    ]
                );

                $wallet->addController($userB);
            }
        );

        $userB = User::firstOrCreate(
            [
                'email' => 'UserAccountB@UserAccount.com'
            ]
        );

        $this->assertTrue($userB->accounts()->get()[0]->id === $userA->wallets()->get()[0]->id);
    }
}
