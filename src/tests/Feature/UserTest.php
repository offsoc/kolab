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

        $this->deleteTestUser('user-test@' . \config('app.domain'));
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('UserAccountC@UserAccount.com');
        $this->deleteTestDomain('UserAccount.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-test@' . \config('app.domain'));
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
     * Tests for User::assignSku()
     */
    public function testAssignSku(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * Verify a wallet assigned a controller is among the accounts of the assignee.
     */
    public function testAccounts(): void
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
     * Test user create/creating observer
     */
    public function testCreate(): void
    {
        Queue::fake();

        $domain = \config('app.domain');

        $user = User::create(['email' => 'USER-test@' . \strtoupper($domain)]);

        $result = User::where('email', 'user-test@' . $domain)->first();

        $this->assertSame('user-test@' . $domain, $result->email);
        $this->assertSame($user->id, $result->id);
        $this->assertSame(User::STATUS_NEW | User::STATUS_ACTIVE, $result->status);
    }

    /**
     * Verify user creation process
     */
    public function testCreateJobs(): void
    {
        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        $user = User::create([
                'email' => 'user-test@' . \config('app.domain')
        ]);

        Queue::assertPushed(\App\Jobs\User\CreateJob::class, 1);

        Queue::assertPushed(
            \App\Jobs\User\CreateJob::class,
            function ($job) use ($user) {
                $userEmail = TestCase::getObjectProperty($job, 'userEmail');
                $userId = TestCase::getObjectProperty($job, 'userId');

                return $userEmail === $user->email
                    && $userId === $user->id;
            }
        );

        Queue::assertPushedWithChain(
            \App\Jobs\User\CreateJob::class,
            [
                \App\Jobs\User\VerifyJob::class,
            ]
        );
/*
        FIXME: Looks like we can't really do detailed assertions on chained jobs
               Another thing to consider is if we maybe should run these jobs
               independently (not chained) and make sure there's no race-condition
               in status update

        Queue::assertPushed(\App\Jobs\User\VerifyJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\VerifyJob::class, function ($job) use ($user) {
            $userEmail = TestCase::getObjectProperty($job, 'userEmail');
            $userId = TestCase::getObjectProperty($job, 'userId');

            return $userEmail === $user->email
                && $userId === $user->id;
        });
*/
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

        $user = $this->getTestUser('user-test@' . \config('app.domain'));
        $package = \App\Package::where('title', 'kolab')->first();
        $user->assignPackage($package);

        $id = $user->id;

        $this->assertCount(4, $user->entitlements()->get());

        $user->delete();

        $this->assertCount(0, $user->entitlements()->get());
        $this->assertTrue($user->fresh()->trashed());
        $this->assertFalse($user->fresh()->isDeleted());

        // Delete the user for real
        $job = new \App\Jobs\User\DeleteJob($id);
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
     * Tests for User::aliasExists()
     */
    public function testAliasExists(): void
    {
        $this->assertTrue(User::aliasExists('jack.daniels@kolab.org'));

        $this->assertFalse(User::aliasExists('j.daniels@kolab.org'));
        $this->assertFalse(User::aliasExists('john@kolab.org'));
    }

    /**
     * Tests for User::emailExists()
     */
    public function testEmailExists(): void
    {
        $this->assertFalse(User::emailExists('jack.daniels@kolab.org'));
        $this->assertFalse(User::emailExists('j.daniels@kolab.org'));

        $this->assertTrue(User::emailExists('john@kolab.org'));
        $user = User::emailExists('john@kolab.org', true);
        $this->assertSame('john@kolab.org', $user->email);
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

        // A case where two users have the same alias
        $ned = $this->getTestUser('ned@kolab.org');
        $ned->setAliases(['joe.monster@kolab.org']);
        $result = User::findByEmail('joe.monster@kolab.org');
        $this->assertNull($result);
        $ned->setAliases([]);

        // TODO: searching by external email (setting)
        $this->markTestIncomplete();
    }

    /**
     * Test User::name()
     */
    public function testName(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-test@' . \config('app.domain'));

        $this->assertSame('', $user->name());
        $this->assertSame(\config('app.name') . ' User', $user->name(true));

        $user->setSetting('first_name', 'First');

        $this->assertSame('First', $user->name());
        $this->assertSame('First', $user->name(true));

        $user->setSetting('last_name', 'Last');

        $this->assertSame('First Last', $user->name());
        $this->assertSame('First Last', $user->name(true));
    }

    /**
     * Tests for UserAliasesTrait::setAliases()
     */
    public function testSetAliases(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $user = $this->getTestUser('UserAccountA@UserAccount.com');
        $domain = $this->getTestDomain('UserAccount.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);

        $this->assertCount(0, $user->aliases->all());

        // Add an alias
        $user->setAliases(['UserAlias1@UserAccount.com']);

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 1);

        $aliases = $user->aliases()->get();
        $this->assertCount(1, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]['alias']);

        // Add another alias
        $user->setAliases(['UserAlias1@UserAccount.com', 'UserAlias2@UserAccount.com']);

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 2);

        $aliases = $user->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]->alias);
        $this->assertSame('useralias2@useraccount.com', $aliases[1]->alias);

        // Remove an alias
        $user->setAliases(['UserAlias1@UserAccount.com']);

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 3);

        $aliases = $user->aliases()->get();
        $this->assertCount(1, $aliases);
        $this->assertSame('useralias1@useraccount.com', $aliases[0]['alias']);

        // Remove all aliases
        $user->setAliases([]);

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 4);

        $this->assertCount(0, $user->aliases()->get());

        // The test below fail since we removed validation code from the UserAliasObserver
        $this->markTestIncomplete();

        // Test sanity checks in UserAliasObserver
        Queue::fake();

        // Existing user
        $user->setAliases(['john@kolab.org']);
        $this->assertCount(0, $user->aliases()->get());

        // Existing alias (in another account)
        $user->setAliases(['john.doe@kolab.org']);
        $this->assertCount(0, $user->aliases()->get());

        Queue::assertNothingPushed();

        // Existing user (in the same group account)
        $ned = $this->getTestUser('ned@kolab.org');
        $ned->setAliases(['john@kolab.org']);
        $this->assertCount(0, $ned->aliases()->get());

        // Existing alias (in the same group account)
        $ned = $this->getTestUser('ned@kolab.org');
        $ned->setAliases(['john.doe@kolab.org']);
        $this->assertSame('john.doe@kolab.org', $ned->aliases()->first()->alias);

        // Existing alias (in another account, public domain)
        $user->setAliases(['alias@kolabnow.com']);
        $ned->setAliases(['alias@kolabnow.com']);
        $this->assertCount(0, $ned->aliases()->get());

        // cleanup
        $ned->setAliases([]);
    }

    /**
     * Tests for UserSettingsTrait::setSettings() and getSetting()
     */
    public function testUserSettings(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $user = $this->getTestUser('UserAccountA@UserAccount.com');

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 0);

        // Test default settings
        // Note: Technicly this tests UserObserver::created() behavior
        $all_settings = $user->settings()->orderBy('key')->get();
        $this->assertCount(2, $all_settings);
        $this->assertSame('country', $all_settings[0]->key);
        $this->assertSame('CH', $all_settings[0]->value);
        $this->assertSame('currency', $all_settings[1]->key);
        $this->assertSame('CHF', $all_settings[1]->value);

        // Add a setting
        $user->setSetting('first_name', 'Firstname');

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 1);

        // Note: We test both current user as well as fresh user object
        //       to make sure cache works as expected
        $this->assertSame('Firstname', $user->getSetting('first_name'));
        $this->assertSame('Firstname', $user->fresh()->getSetting('first_name'));

        // Update a setting
        $user->setSetting('first_name', 'Firstname1');

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 2);

        // Note: We test both current user as well as fresh user object
        //       to make sure cache works as expected
        $this->assertSame('Firstname1', $user->getSetting('first_name'));
        $this->assertSame('Firstname1', $user->fresh()->getSetting('first_name'));

        // Delete a setting (null)
        $user->setSetting('first_name', null);

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 3);

        // Note: We test both current user as well as fresh user object
        //       to make sure cache works as expected
        $this->assertSame(null, $user->getSetting('first_name'));
        $this->assertSame(null, $user->fresh()->getSetting('first_name'));

        // Delete a setting (empty string)
        $user->setSetting('first_name', 'Firstname1');
        $user->setSetting('first_name', '');

        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 5);

        // Note: We test both current user as well as fresh user object
        //       to make sure cache works as expected
        $this->assertSame(null, $user->getSetting('first_name'));
        $this->assertSame(null, $user->fresh()->getSetting('first_name'));

        // Set multiple settings at once
        $user->setSettings([
                'first_name' => 'Firstname2',
                'last_name' => 'Lastname2',
                'country' => null,
        ]);

        // TODO: This really should create a single UserUpdate job, not 3
        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 7);

        // Note: We test both current user as well as fresh user object
        //       to make sure cache works as expected
        $this->assertSame('Firstname2', $user->getSetting('first_name'));
        $this->assertSame('Firstname2', $user->fresh()->getSetting('first_name'));
        $this->assertSame('Lastname2', $user->getSetting('last_name'));
        $this->assertSame('Lastname2', $user->fresh()->getSetting('last_name'));
        $this->assertSame(null, $user->getSetting('country'));
        $this->assertSame(null, $user->fresh()->getSetting('country'));

        $all_settings = $user->settings()->orderBy('key')->get();
        $this->assertCount(3, $all_settings);
    }

    /**
     * Tests for User::users()
     */
    public function testUsers(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $joe = $this->getTestUser('joe@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $wallet = $john->wallets()->first();

        $users = $john->users()->orderBy('email')->get();

        $this->assertCount(4, $users);
        $this->assertEquals($jack->id, $users[0]->id);
        $this->assertEquals($joe->id, $users[1]->id);
        $this->assertEquals($john->id, $users[2]->id);
        $this->assertEquals($ned->id, $users[3]->id);
        $this->assertSame($wallet->id, $users[0]->wallet_id);
        $this->assertSame($wallet->id, $users[1]->wallet_id);
        $this->assertSame($wallet->id, $users[2]->wallet_id);
        $this->assertSame($wallet->id, $users[3]->wallet_id);

        $users = $jack->users()->orderBy('email')->get();

        $this->assertCount(0, $users);

        $users = $ned->users()->orderBy('email')->get();

        $this->assertCount(4, $users);
    }

    public function testWallets(): void
    {
        $this->markTestIncomplete();
    }
}
