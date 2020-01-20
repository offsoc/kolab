<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * Verify a wallet assigned a controller is among the accounts of the assignee.
     */
    public function testListUserAccounts(): void
    {
        $userA = User::firstOrCreate(['email' => 'UserAccountA@UserAccount.com']);

        $this->assertTrue($userA->wallets()->count() == 1);

        $userA->wallets()->each(
            function ($wallet) {
                $userB = User::firstOrCreate(['email' => 'UserAccountB@UserAccount.com']);

                $wallet->addController($userB);
            }
        );

        $userB = User::firstOrCreate(['email' => 'UserAccountB@UserAccount.com']);

        $this->assertTrue($userB->accounts()->get()[0]->id === $userA->wallets()->get()[0]->id);
    }

    public function testUserDomains(): void
    {
        $user = User::firstOrCreate(['email' => 'john@kolab.org']);

        $domains = [];

        foreach ($user->domains() as $domain) {
            $domains[] = $domain->namespace;
        }

        $this->assertContains('kolabnow.com', $domains);
        $this->assertContains('kolab.org', $domains);
    }

    /**
     * Tests for User::findByEmail()
     */
    public function testFindByEmail(): void
    {
        $user = User::firstOrCreate(['email' => 'john@kolab.org']);

        $result = User::findByEmail('john');
        $this->assertNull($result);

        $result = User::findByEmail('non-existing@email.com');
        $this->assertNull($result);

        $result = User::findByEmail('john@kolab.org');
        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);

        // TODO: Make sure searching is case-insensitive
        // TODO: Alias, eternal email
    }
}
