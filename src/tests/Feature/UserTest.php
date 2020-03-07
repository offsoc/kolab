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
     * Tests for User::assignPackage()
     */
    public function testAssignPackage(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests for User::assignPlan()
     */
    public function testAssignPlan(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * Verify user creation process
     */
    public function testUserCreateJob(): void
    {
        // Fake the queue, assert that no jobs were pushed...
        $queue = Queue::fake();
        $queue->assertNothingPushed();

        $user = User::create([
                'email' => 'user-create-test@' . \config('app.domain')
        ]);

        $queue->assertPushed(\App\Jobs\UserCreate::class, 1);
        $queue->assertPushed(\App\Jobs\UserCreate::class, function ($job) use ($user) {
            $job_user = TestCase::getObjectProperty($job, 'user');

            return $job_user->id === $user->id
                && $job_user->email === $user->email;
        });

        $queue->assertPushedWithChain(\App\Jobs\UserCreate::class, [
            \App\Jobs\UserVerify::class,
        ]);
/*
        FIXME: Looks like we can't really do detailed assertions on chained jobs
               Another thing to consider is if we maybe should run these jobs
               independently (not chained) and make sure there's no race-condition
               in status update

        $queue->assertPushed(\App\Jobs\UserVerify::class, 1);
        $queue->assertPushed(\App\Jobs\UserVerify::class, function ($job) use ($user) {
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

    /**
     * Tests for User::controller()
     */
    public function testController(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $this->assertSame($john->id, $john->controller()->id);

        $jack = $this->getTestUser('jack@kolab.org');

        $this->assertSame($john->id, $jack->controller()->id);
    }

    /**
     * Tests for User::domains()
     */
    public function testDomains(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $domains = [];

        foreach ($user->domains() as $domain) {
            $domains[] = $domain->namespace;
        }

        $this->assertContains(\config('app.domain'), $domains);
        $this->assertContains('kolab.org', $domains);

        // Jack is not the wallet controller, so for him the list should not
        // include John's domains, kolab.org specifically
        $user = $this->getTestUser('jack@kolab.org');
        $domains = [];

        foreach ($user->domains() as $domain) {
            $domains[] = $domain->namespace;
        }

        $this->assertContains(\config('app.domain'), $domains);
        $this->assertNotContains('kolab.org', $domains);
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

        // Use an alias
        $result = User::findByEmail('john.doe@kolab.org');
        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);

        // TODO: searching by external email (setting)
        $this->markTestIncomplete();
    }

    /**
     * Tests for UserAliasesTrait::setAliases()
     */
    public function testSetAliases(): void
    {
        Queue::fake();

        $user = $this->getTestUser('UserAccountA@UserAccount.com');

        $this->assertCount(0, $user->aliases->all());

        // Add an alias
        $user->setAliases(['UserAlias1@UserAccount.com']);

        $aliases = $user->aliases()->get();
        $this->assertCount(1, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]['alias']);

        // Add another alias
        $user->setAliases(['UserAlias1@UserAccount.com', 'UserAlias2@UserAccount.com']);

        $aliases = $user->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]->alias);
        $this->assertSame('useralias2@useraccount.com', $aliases[1]->alias);

        // Remove an alias
        $user->setAliases(['UserAlias1@UserAccount.com']);

        $aliases = $user->aliases()->get();
        $this->assertCount(1, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]['alias']);

        // Remove all aliases
        $user->setAliases([]);

        $this->assertCount(0, $user->aliases()->get());

        // TODO: Test that the changes are propagated to ldap
    }

    /**
     * Tests for UserSettingsTrait::setSettings()
     */
    public function testSetSettings(): void
    {
        $this->markTestIncomplete();
    }
}
