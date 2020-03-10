<?php

namespace Tests\Feature;

use App\Domain;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-create-test@' . \config('app.domain'));
        $this->deleteTestUser('userdeletejob@kolabnow.com');
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('UserAccountC@UserAccount.com');
        $this->deleteTestDomain('UserAccount.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-create-test@' . \config('app.domain'));
        $this->deleteTestUser('userdeletejob@kolabnow.com');
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('UserAccountC@UserAccount.com');
        $this->deleteTestDomain('UserAccount.com');

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

    public function testAccounts(): void
    {
        $this->markTestIncomplete();
    }

    public function testCanDelete(): void
    {
        $this->markTestIncomplete();
    }

    public function testCanRead(): void
    {
        $this->markTestIncomplete();
    }

    public function testCanUpdate(): void
    {
        $this->markTestIncomplete();
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
        // TODO: This test does not test much, probably could be removed
        //       or moved to somewhere else, or extended with
        //       other entitlements() related cases.

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
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('userdeletejob@kolabnow.com');
        $package = \App\Package::where('title', 'kolab')->first();
        $user->assignPackage($package);

        $id = $user->id;

        $entitlements = \App\Entitlement::where('owner_id', $id)->get();
        $this->assertCount(4, $entitlements);

        $user->delete();

        $entitlements = \App\Entitlement::where('owner_id', $id)->get();
        $this->assertCount(0, $entitlements);
        $this->assertTrue($user->fresh()->trashed());
        $this->assertFalse($user->fresh()->isDeleted());

        // Delete the user for real
        $job = new \App\Jobs\UserDelete($id);
        $job->handle();

        $this->assertTrue(User::withTrashed()->where('id', $id)->first()->isDeleted());

        $user->forceDelete();

        $this->assertCount(0, User::withTrashed()->where('id', $id)->get());

        // Test an account with users
        $userA = $this->getTestUser('UserAccountA@UserAccount.com');
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');
        $userC = $this->getTestUser('UserAccountC@UserAccount.com');
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $domain = $this->getTestDomain('UserAccount.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);
        $userA->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $userA);
        $userA->assignPackage($package_kolab, $userB);
        $userA->assignPackage($package_kolab, $userC);

        $entitlementsA = \App\Entitlement::where('entitleable_id', $userA->id);
        $entitlementsB = \App\Entitlement::where('entitleable_id', $userB->id);
        $entitlementsC = \App\Entitlement::where('entitleable_id', $userC->id);
        $entitlementsDomain = \App\Entitlement::where('entitleable_id', $domain->id);
        $this->assertSame(4, $entitlementsA->count());
        $this->assertSame(4, $entitlementsB->count());
        $this->assertSame(4, $entitlementsC->count());
        $this->assertSame(1, $entitlementsDomain->count());

        // Delete non-controller user
        $userC->delete();

        $this->assertTrue($userC->fresh()->trashed());
        $this->assertFalse($userC->fresh()->isDeleted());
        $this->assertSame(0, $entitlementsC->count());

        // Delete the controller (and expect "sub"-users to be deleted too)
        $userA->delete();

        $this->assertSame(0, $entitlementsA->count());
        $this->assertSame(0, $entitlementsB->count());
        $this->assertSame(0, $entitlementsDomain->count());
        $this->assertTrue($userA->fresh()->trashed());
        $this->assertTrue($userB->fresh()->trashed());
        $this->assertTrue($domain->fresh()->trashed());
        $this->assertFalse($userA->isDeleted());
        $this->assertFalse($userB->isDeleted());
        $this->assertFalse($domain->isDeleted());
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

    /**
     * Tests for User::users()
     */
    public function testUsers(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $wallet = $john->wallets()->first();

        $users = $john->users()->orderBy('email')->get();

        $this->assertCount(3, $users);
        $this->assertEquals($jack->id, $users[0]->id);
        $this->assertEquals($john->id, $users[1]->id);
        $this->assertEquals($ned->id, $users[2]->id);
        $this->assertSame($wallet->id, $users[0]->wallet_id);
        $this->assertSame($wallet->id, $users[1]->wallet_id);
        $this->assertSame($wallet->id, $users[2]->wallet_id);

        $users = $jack->users()->orderBy('email')->get();

        $this->assertCount(0, $users);

        $users = $ned->users()->orderBy('email')->get();

        $this->assertCount(3, $users);
    }

    public function testWallets(): void
    {
        $this->markTestIncomplete();
    }
}
