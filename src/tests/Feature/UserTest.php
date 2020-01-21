<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        User::where('email', 'user-create-test@' . \config('app.domain'))->delete();
    }

    /**
     * Verify user creation process
     */
    public function testUserCreateJob(): void
    {
        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        $user = User::create([
                'email' => 'user-create-test@' . \config('app.domain')
        ]);

        Queue::assertPushed(\App\Jobs\ProcessUserCreate::class, 1);
        Queue::assertPushed(\App\Jobs\ProcessUserCreate::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->id === $user->id
                && $job_user->email === $user->email;
        });
    }

    /**
     * Verify a wallet assigned a controller is among the accounts of the assignee.
     */
    public function testListUserAccounts(): void
    {
        $userA = $this->getTestUser('UserAccountA@UserAccount.com');
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');

        $this->assertTrue($userA->wallets()->count() == 1);

        $userA->wallets()->each(
            function ($wallet) use ($userB) {
                $wallet->addController($userB);
            }
        );

        $this->assertTrue($userB->accounts()->get()[0]->id === $userA->wallets()->get()[0]->id);
    }

    public function testUserDomains(): void
    {
        $user = $this->getTestUser('john@kolab.org');
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
        $user = $this->getTestUser('john@kolab.org');

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
