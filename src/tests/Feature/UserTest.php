<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
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

    public function testUserDomains()
    {
        $user = User::firstOrCreate(
            [
                'email' => 'john@kolab.org'
            ]
        );

        $domains = [];

        foreach ($user->domains() as $domain) {
            $domains[] = $domain->namespace;
        }

        $this->assertTrue(in_array('kolabnow.com', $domains));
        $this->assertTrue(in_array('kolab.org', $domains));
    }

    public function testFindByEmail()
    {
        $this->markTestIncomplete('TODO');
    }
}
