<?php

namespace Tests\Feature;

use App\Domain;
use App\Group;
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
        $this->deleteTestGroup('test-group@UserAccount.com');
        $this->deleteTestDomain('UserAccount.com');
        $this->deleteTestDomain('UserAccountAdd.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-test@' . \config('app.domain'));
        $this->deleteTestUser('UserAccountA@UserAccount.com');
        $this->deleteTestUser('UserAccountB@UserAccount.com');
        $this->deleteTestUser('UserAccountC@UserAccount.com');
        $this->deleteTestGroup('test-group@UserAccount.com');
        $this->deleteTestDomain('UserAccount.com');
        $this->deleteTestDomain('UserAccountAdd.com');

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

    /**
     * Test User::canRead() method
     */
    public function testCanRead(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $domain = $this->getTestDomain('kolab.org');

        // Admin
        $this->assertTrue($admin->canRead($admin));
        $this->assertTrue($admin->canRead($john));
        $this->assertTrue($admin->canRead($jack));
        $this->assertTrue($admin->canRead($reseller1));
        $this->assertTrue($admin->canRead($reseller2));
        $this->assertTrue($admin->canRead($domain));
        $this->assertTrue($admin->canRead($domain->wallet()));

        // Reseller - kolabnow
        $this->assertTrue($reseller1->canRead($john));
        $this->assertTrue($reseller1->canRead($jack));
        $this->assertTrue($reseller1->canRead($reseller1));
        $this->assertTrue($reseller1->canRead($domain));
        $this->assertTrue($reseller1->canRead($domain->wallet()));
        $this->assertFalse($reseller1->canRead($reseller2));
        $this->assertFalse($reseller1->canRead($admin));

        // Reseller - different tenant
        $this->assertTrue($reseller2->canRead($reseller2));
        $this->assertFalse($reseller2->canRead($john));
        $this->assertFalse($reseller2->canRead($jack));
        $this->assertFalse($reseller2->canRead($reseller1));
        $this->assertFalse($reseller2->canRead($domain));
        $this->assertFalse($reseller2->canRead($domain->wallet()));
        $this->assertFalse($reseller2->canRead($admin));

        // Normal user - account owner
        $this->assertTrue($john->canRead($john));
        $this->assertTrue($john->canRead($ned));
        $this->assertTrue($john->canRead($jack));
        $this->assertTrue($john->canRead($domain));
        $this->assertTrue($john->canRead($domain->wallet()));
        $this->assertFalse($john->canRead($reseller1));
        $this->assertFalse($john->canRead($reseller2));
        $this->assertFalse($john->canRead($admin));

        // Normal user - a non-owner and non-controller
        $this->assertTrue($jack->canRead($jack));
        $this->assertFalse($jack->canRead($john));
        $this->assertFalse($jack->canRead($domain));
        $this->assertFalse($jack->canRead($domain->wallet()));
        $this->assertFalse($jack->canRead($reseller1));
        $this->assertFalse($jack->canRead($reseller2));
        $this->assertFalse($jack->canRead($admin));

        // Normal user - John's wallet controller
        $this->assertTrue($ned->canRead($ned));
        $this->assertTrue($ned->canRead($john));
        $this->assertTrue($ned->canRead($jack));
        $this->assertTrue($ned->canRead($domain));
        $this->assertTrue($ned->canRead($domain->wallet()));
        $this->assertFalse($ned->canRead($reseller1));
        $this->assertFalse($ned->canRead($reseller2));
        $this->assertFalse($ned->canRead($admin));
    }

    /**
     * Test User::canUpdate() method
     */
    public function testCanUpdate(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $domain = $this->getTestDomain('kolab.org');

        // Admin
        $this->assertTrue($admin->canUpdate($admin));
        $this->assertTrue($admin->canUpdate($john));
        $this->assertTrue($admin->canUpdate($jack));
        $this->assertTrue($admin->canUpdate($reseller1));
        $this->assertTrue($admin->canUpdate($reseller2));
        $this->assertTrue($admin->canUpdate($domain));
        $this->assertTrue($admin->canUpdate($domain->wallet()));

        // Reseller - kolabnow
        $this->assertTrue($reseller1->canUpdate($john));
        $this->assertTrue($reseller1->canUpdate($jack));
        $this->assertTrue($reseller1->canUpdate($reseller1));
        $this->assertTrue($reseller1->canUpdate($domain));
        $this->assertTrue($reseller1->canUpdate($domain->wallet()));
        $this->assertFalse($reseller1->canUpdate($reseller2));
        $this->assertFalse($reseller1->canUpdate($admin));

        // Reseller - different tenant
        $this->assertTrue($reseller2->canUpdate($reseller2));
        $this->assertFalse($reseller2->canUpdate($john));
        $this->assertFalse($reseller2->canUpdate($jack));
        $this->assertFalse($reseller2->canUpdate($reseller1));
        $this->assertFalse($reseller2->canUpdate($domain));
        $this->assertFalse($reseller2->canUpdate($domain->wallet()));
        $this->assertFalse($reseller2->canUpdate($admin));

        // Normal user - account owner
        $this->assertTrue($john->canUpdate($john));
        $this->assertTrue($john->canUpdate($ned));
        $this->assertTrue($john->canUpdate($jack));
        $this->assertTrue($john->canUpdate($domain));
        $this->assertFalse($john->canUpdate($domain->wallet()));
        $this->assertFalse($john->canUpdate($reseller1));
        $this->assertFalse($john->canUpdate($reseller2));
        $this->assertFalse($john->canUpdate($admin));

        // Normal user - a non-owner and non-controller
        $this->assertTrue($jack->canUpdate($jack));
        $this->assertFalse($jack->canUpdate($john));
        $this->assertFalse($jack->canUpdate($domain));
        $this->assertFalse($jack->canUpdate($domain->wallet()));
        $this->assertFalse($jack->canUpdate($reseller1));
        $this->assertFalse($jack->canUpdate($reseller2));
        $this->assertFalse($jack->canUpdate($admin));

        // Normal user - John's wallet controller
        $this->assertTrue($ned->canUpdate($ned));
        $this->assertTrue($ned->canUpdate($john));
        $this->assertTrue($ned->canUpdate($jack));
        $this->assertTrue($ned->canUpdate($domain));
        $this->assertFalse($ned->canUpdate($domain->wallet()));
        $this->assertFalse($ned->canUpdate($reseller1));
        $this->assertFalse($ned->canUpdate($reseller2));
        $this->assertFalse($ned->canUpdate($admin));
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

        $domain = $this->getTestDomain('useraccount.com', [
                'status' => Domain::STATUS_NEW | Domain::STATUS_ACTIVE,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $domains = collect($user->domains())->pluck('namespace')->all();

        $this->assertContains($domain->namespace, $domains);
        $this->assertContains('kolab.org', $domains);

        // Jack is not the wallet controller, so for him the list should not
        // include John's domains, kolab.org specifically
        $user = $this->getTestUser('jack@kolab.org');

        $domains = collect($user->domains())->pluck('namespace')->all();

        $this->assertContains($domain->namespace, $domains);
        $this->assertNotContains('kolab.org', $domains);

        // Public domains of other tenants should not be returned
        $tenant = \App\Tenant::where('id', '!=', \config('app.tenant_id'))->first();
        $domain->tenant_id = $tenant->id;
        $domain->save();

        $domains = collect($user->domains())->pluck('namespace')->all();

        $this->assertNotContains($domain->namespace, $domains);
    }

    /**
     * Test User::hasSku() method
     */
    public function testHasSku(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $this->assertTrue($john->hasSku('mailbox'));
        $this->assertTrue($john->hasSku('storage'));
        $this->assertFalse($john->hasSku('beta'));
        $this->assertFalse($john->hasSku('unknown'));
    }

    public function testUserQuota(): void
    {
        // TODO: This test does not test much, probably could be removed
        //       or moved to somewhere else, or extended with
        //       other entitlements() related cases.

        $user = $this->getTestUser('john@kolab.org');
        $storage_sku = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $count = 0;

        foreach ($user->entitlements()->get() as $entitlement) {
            if ($entitlement->sku_id == $storage_sku->id) {
                $count += 1;
            }
        }

        $this->assertTrue($count == 5);
    }

    /**
     * Test user deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-test@' . \config('app.domain'));
        $package = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $user->assignPackage($package);

        $id = $user->id;

        $this->assertCount(7, $user->entitlements()->get());

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

        // Test an account with users, domain, and group
        $userA = $this->getTestUser('UserAccountA@UserAccount.com');
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');
        $userC = $this->getTestUser('UserAccountC@UserAccount.com');
        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $domain = $this->getTestDomain('UserAccount.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);
        $userA->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $userA);
        $userA->assignPackage($package_kolab, $userB);
        $userA->assignPackage($package_kolab, $userC);
        $group = $this->getTestGroup('test-group@UserAccount.com');
        $group->assignToWallet($userA->wallets->first());

        $entitlementsA = \App\Entitlement::where('entitleable_id', $userA->id);
        $entitlementsB = \App\Entitlement::where('entitleable_id', $userB->id);
        $entitlementsC = \App\Entitlement::where('entitleable_id', $userC->id);
        $entitlementsDomain = \App\Entitlement::where('entitleable_id', $domain->id);
        $entitlementsGroup = \App\Entitlement::where('entitleable_id', $group->id);

        $this->assertSame(7, $entitlementsA->count());
        $this->assertSame(7, $entitlementsB->count());
        $this->assertSame(7, $entitlementsC->count());
        $this->assertSame(1, $entitlementsDomain->count());
        $this->assertSame(1, $entitlementsGroup->count());

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
        $this->assertSame(0, $entitlementsGroup->count());
        $this->assertTrue($userA->fresh()->trashed());
        $this->assertTrue($userB->fresh()->trashed());
        $this->assertTrue($domain->fresh()->trashed());
        $this->assertTrue($group->fresh()->trashed());
        $this->assertFalse($userA->isDeleted());
        $this->assertFalse($userB->isDeleted());
        $this->assertFalse($domain->isDeleted());
        $this->assertFalse($group->isDeleted());

        $userA->forceDelete();

        $all_entitlements = \App\Entitlement::where('wallet_id', $userA->wallets->first()->id);

        $this->assertSame(0, $all_entitlements->withTrashed()->count());
        $this->assertCount(0, User::withTrashed()->where('id', $userA->id)->get());
        $this->assertCount(0, User::withTrashed()->where('id', $userB->id)->get());
        $this->assertCount(0, User::withTrashed()->where('id', $userC->id)->get());
        $this->assertCount(0, Domain::withTrashed()->where('id', $domain->id)->get());
        $this->assertCount(0, Group::withTrashed()->where('id', $group->id)->get());
    }

    /**
     * Test user deletion vs. group membership
     */
    public function testDeleteAndGroups(): void
    {
        Queue::fake();

        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $userA = $this->getTestUser('UserAccountA@UserAccount.com');
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');
        $userA->assignPackage($package_kolab, $userB);
        $group = $this->getTestGroup('test-group@UserAccount.com');
        $group->members = ['test@gmail.com', $userB->email];
        $group->assignToWallet($userA->wallets->first());
        $group->save();

        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 1);

        $userGroups = $userA->groups()->get();
        $this->assertSame(1, $userGroups->count());
        $this->assertSame($group->id, $userGroups->first()->id);

        $userB->delete();

        $this->assertSame(['test@gmail.com'], $group->fresh()->members);

        // Twice, one for save() and one for delete() above
        Queue::assertPushed(\App\Jobs\Group\UpdateJob::class, 2);
    }

    /**
     * Test handling negative balance on user deletion
     */
    public function testDeleteWithNegativeBalance(): void
    {
        $user = $this->getTestUser('user-test@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->balance = -1000;
        $wallet->save();
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();
        \App\Transaction::where('object_id', $reseller_wallet->id)->where('object_type', \App\Wallet::class)->delete();

        $user->delete();

        $reseller_transactions = \App\Transaction::where('object_id', $reseller_wallet->id)
            ->where('object_type', \App\Wallet::class)->get();

        $this->assertSame(-1000, $reseller_wallet->fresh()->balance);
        $this->assertCount(1, $reseller_transactions);
        $trans = $reseller_transactions[0];
        $this->assertSame("Deleted user {$user->email}", $trans->description);
        $this->assertSame(-1000, $trans->amount);
        $this->assertSame(\App\Transaction::WALLET_DEBIT, $trans->type);
    }

    /**
     * Test handling positive balance on user deletion
     */
    public function testDeleteWithPositiveBalance(): void
    {
        $user = $this->getTestUser('user-test@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $wallet->balance = 1000;
        $wallet->save();
        $reseller_wallet = $user->tenant->wallet();
        $reseller_wallet->balance = 0;
        $reseller_wallet->save();

        $user->delete();

        $this->assertSame(0, $reseller_wallet->fresh()->balance);
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
        $this->assertSame($user->tenant->title . ' User', $user->name(true));

        $user->setSetting('first_name', 'First');

        $this->assertSame('First', $user->name());
        $this->assertSame('First', $user->name(true));

        $user->setSetting('last_name', 'Last');

        $this->assertSame('First Last', $user->name());
        $this->assertSame('First Last', $user->name(true));
    }

    /**
     * Test user restoring
     */
    public function testRestore(): void
    {
        Queue::fake();

        // Test an account with users and domain
        $userA = $this->getTestUser('UserAccountA@UserAccount.com', [
                'status' => User::STATUS_LDAP_READY | User::STATUS_IMAP_READY | User::STATUS_SUSPENDED,
        ]);
        $userB = $this->getTestUser('UserAccountB@UserAccount.com');
        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $domainA = $this->getTestDomain('UserAccount.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);
        $domainB = $this->getTestDomain('UserAccountAdd.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_HOSTED,
        ]);
        $userA->assignPackage($package_kolab);
        $domainA->assignPackage($package_domain, $userA);
        $domainB->assignPackage($package_domain, $userA);
        $userA->assignPackage($package_kolab, $userB);

        $storage_sku = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $now = \Carbon\Carbon::now();
        $wallet_id = $userA->wallets->first()->id;

        // add an extra storage entitlement
        $ent1 = \App\Entitlement::create([
                'wallet_id' => $wallet_id,
                'sku_id' => $storage_sku->id,
                'cost' => 0,
                'entitleable_id' => $userA->id,
                'entitleable_type' => User::class,
        ]);

        $entitlementsA = \App\Entitlement::where('entitleable_id', $userA->id);
        $entitlementsB = \App\Entitlement::where('entitleable_id', $userB->id);
        $entitlementsDomain = \App\Entitlement::where('entitleable_id', $domainA->id);

        // First delete the user
        $userA->delete();

        $this->assertSame(0, $entitlementsA->count());
        $this->assertSame(0, $entitlementsB->count());
        $this->assertSame(0, $entitlementsDomain->count());
        $this->assertTrue($userA->fresh()->trashed());
        $this->assertTrue($userB->fresh()->trashed());
        $this->assertTrue($domainA->fresh()->trashed());
        $this->assertTrue($domainB->fresh()->trashed());
        $this->assertFalse($userA->isDeleted());
        $this->assertFalse($userB->isDeleted());
        $this->assertFalse($domainA->isDeleted());

        // Backdate one storage entitlement (it's not expected to be restored)
        \App\Entitlement::withTrashed()->where('id', $ent1->id)
            ->update(['deleted_at' => $now->copy()->subMinutes(2)]);

        // Backdate entitlements to assert that they were restored with proper updated_at timestamp
        \App\Entitlement::withTrashed()->where('wallet_id', $wallet_id)
            ->update(['updated_at' => $now->subMinutes(10)]);

        Queue::fake();

        // Then restore it
        $userA->restore();
        $userA->refresh();

        $this->assertFalse($userA->trashed());
        $this->assertFalse($userA->isDeleted());
        $this->assertFalse($userA->isSuspended());
        $this->assertFalse($userA->isLdapReady());
        $this->assertFalse($userA->isImapReady());
        $this->assertTrue($userA->isActive());

        $this->assertTrue($userB->fresh()->trashed());
        $this->assertTrue($domainB->fresh()->trashed());
        $this->assertFalse($domainA->fresh()->trashed());

        // Assert entitlements
        $this->assertSame(7, $entitlementsA->count()); // mailbox + groupware + 5 x storage
        $this->assertTrue($ent1->fresh()->trashed());
        $entitlementsA->get()->each(function ($ent) {
            $this->assertTrue($ent->updated_at->greaterThan(\Carbon\Carbon::now()->subSeconds(5)));
        });

        // We expect only CreateJob + UpdateJob pair for both user and domain.
        // Because how Illuminate/Database/Eloquent/SoftDeletes::restore() method
        // is implemented we cannot skip the UpdateJob in any way.
        // I don't want to overwrite this method, the extra job shouldn't do any harm.
        $this->assertCount(4, Queue::pushedJobs()); // @phpstan-ignore-line
        Queue::assertPushed(\App\Jobs\Domain\UpdateJob::class, 1);
        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\UpdateJob::class, 1);
        Queue::assertPushed(\App\Jobs\User\CreateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\User\CreateJob::class,
            function ($job) use ($userA) {
                return $userA->id === TestCase::getObjectProperty($job, 'userId');
            }
        );
        Queue::assertPushedWithChain(
            \App\Jobs\User\CreateJob::class,
            [
                \App\Jobs\User\VerifyJob::class,
            ]
        );
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
    }

    /**
     * Tests for UserSettingsTrait::setSettings() and getSetting() and getSettings()
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

        // Test getSettings() method
        $this->assertSame(
            [
                'first_name' => 'Firstname2',
                'last_name' => 'Lastname2',
                'unknown' => null,
            ],
            $user->getSettings(['first_name', 'last_name', 'unknown'])
        );
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
