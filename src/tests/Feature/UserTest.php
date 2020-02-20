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

        $this->deleteTestUser('user-create-test@' . \config('app.domain'));
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('userdeletejob@kolabnow.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-create-test@' . \config('app.domain'));
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('userdeletejob@kolabnow.com');

        parent::tearDown();
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

        Queue::assertPushed(\App\Jobs\UserCreate::class, 1);
        Queue::assertPushed(\App\Jobs\UserCreate::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->id === $user->id
                && $job_user->email === $user->email;
        });

        Queue::assertPushedWithChain(\App\Jobs\UserCreate::class, [
            \App\Jobs\UserVerify::class,
        ]);
/*
        FIXME: Looks like we can't really do detailed assertions on chained jobs
               Another thing to consider is if we maybe should run these jobs
               independently (not chained) and make sure there's no race-condition
               in status update

        Queue::assertPushed(\App\Jobs\UserVerify::class, 1);
        Queue::assertPushed(\App\Jobs\UserVerify::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->id === $user->id
                && $job_user->email === $user->email;
        });
*/
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

    public function testUserQuota(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $storage_sku = \App\Sku::where('title', 'storage')->first();

        $count = 0;

        foreach ($user->entitlements()->get() as $entitlement) {
            if ($entitlement->sku_id == $storage_sku->id) {
                $count += 1;
            }
        }

        $this->assertTrue($count == 2);
    }

    /**
     * Test user deletion
     */
    public function testUserDelete(): void
    {
        $user = $this->getTestUser('userdeletejob@kolabnow.com');

        $package = \App\Package::where('title', 'kolab')->first();

        $user->assignPackage($package);

        $id = $user->id;

        $user->delete();

        $job = new \App\Jobs\UserDelete($id);
        $job->handle();

        $user->forceDelete();

        $entitlements = \App\Entitlement::where('owner_id', 'id')->get();

        $this->assertCount(0, $entitlements);
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
