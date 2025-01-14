<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\EventLog;
use App\Sku;
use App\User;
use App\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainTest extends TestCase
{
    private $domains = [
        'public-active.com',
        'gmail.com',
        'ci-success-cname.kolab.org',
        'ci-success-txt.kolab.org',
        'ci-failure-cname.kolab.org',
        'ci-failure-txt.kolab.org',
        'ci-failure-none.kolab.org',
    ];

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromDate(2022, 02, 02));
        foreach ($this->domains as $domain) {
            $this->deleteTestDomain($domain);
        }

        $this->deleteTestUser('user@gmail.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        foreach ($this->domains as $domain) {
            $this->deleteTestDomain($domain);
        }

        $this->deleteTestUser('user@gmail.com');

        parent::tearDown();
    }

    /**
     * Tests for Domain::assignPackage()
     */
    public function testAssignPackage(): void
    {
        $user = $this->getTestUser('user@gmail.com');
        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        $package = \App\Package::withObjectTenantContext($user)->where('title', 'domain-hosting')->first();
        $wallet = $user->wallets()->first();

        $domain->assignPackage($package, $user);

        $this->assertCount(1, $entitlements = $wallet->entitlements()->get());
        $this->assertSame(0, $entitlements[0]->cost);

        // Assert that units_free might not work as we intended to
        // The second domain is still free, but it should cost 100.
        $domain = $this->getTestDomain('public-active.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        $domain->assignPackage($package, $user);

        $this->assertCount(2, $entitlements = $wallet->entitlements()->get());
        $this->assertSame(0, $entitlements[0]->cost);
        $this->assertSame(0, $entitlements[1]->cost);

        // Make assigning domain that is already assigned is not possible
        $this->expectException(\Exception::class);
        $domain->assignPackage($package, $user);
    }

    /**
     * Test domain create/creating observer
     */
    public function testCreate(): void
    {
        Queue::fake();

        $domain = Domain::create([
                'namespace' => 'GMAIL.COM',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        $result = Domain::where('namespace', 'gmail.com')->first();

        $this->assertSame('gmail.com', $result->namespace);
        $this->assertSame($domain->id, $result->id);
        $this->assertSame($domain->type, $result->type);
        $this->assertSame(Domain::STATUS_NEW, $result->status);
    }

    /**
     * Test domain creating jobs
     */
    public function testCreateJobs(): void
    {
        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        $domain = Domain::create([
                'namespace' => 'gmail.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);

        Queue::assertPushed(
            \App\Jobs\Domain\CreateJob::class,
            function ($job) use ($domain) {
                $domainId = TestCase::getObjectProperty($job, 'domainId');
                $domainNamespace = TestCase::getObjectProperty($job, 'domainNamespace');

                return $domainId === $domain->id &&
                    $domainNamespace === $domain->namespace;
            }
        );
    }

    /**
     * Tests getPublicDomains() method
     */
    public function testGetPublicDomains(): void
    {
        $public_domains = Domain::getPublicDomains();

        $this->assertNotContains('public-active.com', $public_domains);

        $queue = Queue::fake();

        $domain = Domain::create([
                'namespace' => 'public-active.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        // External domains should not be returned
        $public_domains = Domain::getPublicDomains();
        $this->assertNotContains('public-active.com', $public_domains);

        $domain->type = Domain::TYPE_PUBLIC;
        $domain->save();

        $public_domains = Domain::getPublicDomains();
        $this->assertContains('public-active.com', $public_domains);

        // Domains of other tenants should not be returned
        $tenant = Tenant::whereNotIn('id', [\config('app.tenant_id')])->first();
        $domain->tenant_id = $tenant->id;
        $domain->save();

        $public_domains = Domain::getPublicDomains();
        $this->assertNotContains('public-active.com', $public_domains);
    }

    /**
     * Test domain (ownership) confirmation
     *
     * @group dns
     */
    public function testConfirm(): void
    {
        /*
            DNS records for positive and negative tests - kolab.org:

            ci-success-cname                A       212.103.80.148
            ci-success-cname                MX      10  mx01.kolabnow.com.
            ci-success-cname                TXT     "v=spf1 mx -all"
            kolab-verify.ci-success-cname   CNAME   2b719cfa4e1033b1e1e132977ed4fe3e.ci-success-cname

            ci-failure-cname                A       212.103.80.148
            ci-failure-cname                MX      10  mx01.kolabnow.com.
            kolab-verify.ci-failure-cname   CNAME   2b719cfa4e1033b1e1e132977ed4fe3e.ci-failure-cname

            ci-success-txt                  A       212.103.80.148
            ci-success-txt                  MX      10  mx01.kolabnow.com.
            ci-success-txt                  TXT     "v=spf1 mx -all"
            ci-success-txt                  TXT     "kolab-verify=de5d04ababb52d52e2519a2f16d11422"

            ci-failure-txt                  A       212.103.80.148
            ci-failure-txt                  MX      10  mx01.kolabnow.com.
            kolab-verify.ci-failure-txt     TXT     "kolab-verify=de5d04ababb52d52e2519a2f16d11422"

            ci-failure-none                 A       212.103.80.148
            ci-failure-none                 MX      10  mx01.kolabnow.com.
        */

        $queue = Queue::fake();

        $domain_props = ['status' => Domain::STATUS_NEW, 'type' => Domain::TYPE_EXTERNAL];

        $domain = $this->getTestDomain('ci-failure-none.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertFalse($domain->isConfirmed());

        $domain = $this->getTestDomain('ci-failure-txt.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertFalse($domain->isConfirmed());

        $domain = $this->getTestDomain('ci-failure-cname.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertFalse($domain->isConfirmed());

        $domain = $this->getTestDomain('ci-success-txt.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm());
        $this->assertTrue($domain->isConfirmed());

        $domain = $this->getTestDomain('ci-success-cname.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm());
        $this->assertTrue($domain->isConfirmed());
    }

    /**
     * Test domain deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $domain->delete();

        $this->assertTrue($domain->fresh()->trashed());
        $this->assertFalse($domain->fresh()->isDeleted());

        Queue::assertPushed(\App\Jobs\Domain\DeleteJob::class, 1);
        Queue::assertPushed(\App\Jobs\Domain\UpdateJob::class, 0);

        // Delete the domain for real
        $job = new \App\Jobs\Domain\DeleteJob($domain->id);
        $job->handle();

        $this->assertTrue(Domain::withTrashed()->where('id', $domain->id)->first()->isDeleted());

        Queue::fake();

        $domain->forceDelete();

        $this->assertCount(0, Domain::withTrashed()->where('id', $domain->id)->get());

        Queue::assertPushed(\App\Jobs\Domain\DeleteJob::class, 0);
        Queue::assertPushed(\App\Jobs\Domain\UpdateJob::class, 0);
    }

    /**
     * Test eventlog on domain deletion
     */
    public function testDeleteAndEventLog(): void
    {
        Queue::fake();

        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        EventLog::createFor($domain, EventLog::TYPE_SUSPENDED, 'test');

        $domain->delete();

        $this->assertCount(1, EventLog::where('object_id', $domain->id)->where('object_type', Domain::class)->get());

        $domain->forceDelete();

        $this->assertCount(0, EventLog::where('object_id', $domain->id)->where('object_type', Domain::class)->get());
    }

    /**
     * Test isEmpty() method
     */
    public function testIsEmpty(): void
    {
        Queue::fake();

        $this->deleteTestUser('user@gmail.com');
        $this->deleteTestGroup('group@gmail.com');
        $this->deleteTestResource('resource@gmail.com');
        $this->deleteTestSharedFolder('folder@gmail.com');

        // Empty domain
        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        $this->assertTrue($domain->isEmpty());

        $this->getTestUser('user@gmail.com');
        $this->assertFalse($domain->isEmpty());
        $this->deleteTestUser('user@gmail.com');
        $this->assertTrue($domain->isEmpty());
        $this->getTestGroup('group@gmail.com');
        $this->assertFalse($domain->isEmpty());
        $this->deleteTestGroup('group@gmail.com');
        $this->assertTrue($domain->isEmpty());
        $this->getTestResource('resource@gmail.com');
        $this->assertFalse($domain->isEmpty());
        $this->deleteTestResource('resource@gmail.com');
        $this->getTestSharedFolder('folder@gmail.com');
        $this->assertFalse($domain->isEmpty());
        $this->deleteTestSharedFolder('folder@gmail.com');

        // TODO: Test with an existing alias, but not other objects in a domain

        // Empty public domain
        $domain = Domain::where('namespace', 'libertymail.net')->first();

        $this->assertFalse($domain->isEmpty());
    }

    /**
     * Test domain restoring
     */
    public function testRestore(): void
    {
        Queue::fake();

        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW | Domain::STATUS_SUSPENDED
                    | Domain::STATUS_LDAP_READY | Domain::STATUS_CONFIRMED,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $user = $this->getTestUser('user@gmail.com');
        $sku = \App\Sku::where('title', 'domain-hosting')->first();
        $now = \Carbon\Carbon::now();

        // Assign two entitlements to the domain, so we can assert that only the
        // ones deleted last will be restored
        $ent1 = \App\Entitlement::create([
                'wallet_id' => $user->wallets->first()->id,
                'sku_id' => $sku->id,
                'cost' => 0,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
        ]);
        $ent2 = \App\Entitlement::create([
                'wallet_id' => $user->wallets->first()->id,
                'sku_id' => $sku->id,
                'cost' => 0,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
        ]);

        $domain->delete();

        $this->assertTrue($domain->fresh()->trashed());
        $this->assertFalse($domain->fresh()->isDeleted());
        $this->assertTrue($ent1->fresh()->trashed());
        $this->assertTrue($ent2->fresh()->trashed());

        // Backdate some properties
        \App\Entitlement::withTrashed()->where('id', $ent2->id)->update(['deleted_at' => $now->subMinutes(2)]);
        \App\Entitlement::withTrashed()->where('id', $ent1->id)->update(['updated_at' => $now->subMinutes(10)]);

        Queue::fake();

        $domain->restore();
        $domain->refresh();

        $this->assertFalse($domain->trashed());
        $this->assertFalse($domain->isDeleted());
        $this->assertFalse($domain->isSuspended());
        $this->assertFalse($domain->isLdapReady());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isConfirmed());
        $this->assertTrue($domain->isNew());

        // Assert entitlements
        $this->assertTrue($ent2->fresh()->trashed());
        $this->assertFalse($ent1->fresh()->trashed());
        $this->assertTrue($ent1->updated_at->greaterThan(\Carbon\Carbon::now()->subSeconds(5)));

        // We expect only one CreateJob and one UpdateJob
        // Because how Illuminate/Database/Eloquent/SoftDeletes::restore() method
        // is implemented we cannot skip the UpdateJob in any way.
        // I don't want to overwrite this method, the extra job shouldn't do any harm.
        $this->assertCount(2, Queue::pushedJobs()); // @phpstan-ignore-line
        Queue::assertPushed(\App\Jobs\Domain\UpdateJob::class, 1);
        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Domain\CreateJob::class,
            function ($job) use ($domain) {
                return $domain->id === TestCase::getObjectProperty($job, 'domainId');
            }
        );
    }

    /**
     * Test domain suspending/unsuspending
     */
    public function testSuspendAndUnsuspend()
    {
        Queue::fake();

        $domain = $this->getTestDomain('gmail.com', ['type' => Domain::TYPE_EXTERNAL]);

        // Verify we can suspend an active domain
        $domain->status = Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED | Domain::STATUS_ACTIVE;

        $this->assertFalse($domain->isSuspended());
        $this->assertTrue($domain->isActive());

        $domain->suspend();

        $this->assertTrue($domain->isSuspended());
        $this->assertFalse($domain->isActive());

        // Verify we can unsuspend a suspended domain
        $domain->status = Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED | Domain::STATUS_SUSPENDED;

        $this->assertTrue($domain->isSuspended());
        $this->assertFalse($domain->isActive());

        $domain->unsuspend();

        $this->assertFalse($domain->isSuspended());
        $this->assertTrue($domain->isActive());

        // Verify we can unsuspend a suspended domain that wasn't confirmed
        $domain->status = Domain::STATUS_NEW | Domain::STATUS_SUSPENDED;

        $this->assertTrue($domain->isNew());
        $this->assertTrue($domain->isSuspended());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isConfirmed());
        $this->assertFalse($domain->isVerified());

        $domain->unsuspend();

        $this->assertTrue($domain->isNew());
        $this->assertFalse($domain->isSuspended());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isConfirmed());
        $this->assertFalse($domain->isVerified());

        // Verify we can unsuspend a suspended domain that was verified but not confirmed
        $domain->status = Domain::STATUS_NEW | Domain::STATUS_SUSPENDED | Domain::STATUS_VERIFIED;

        $this->assertTrue($domain->isNew());
        $this->assertTrue($domain->isSuspended());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isConfirmed());
        $this->assertTrue($domain->isVerified());

        $domain->unsuspend();

        $this->assertTrue($domain->isNew());
        $this->assertFalse($domain->isSuspended());
        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isConfirmed());
        $this->assertTrue($domain->isVerified());
    }

    /**
     * Tests for Domain::walletOwner() (from EntitleableTrait)
     */
    public function testWalletOwner(): void
    {
        $domain = $this->getTestDomain('kolab.org');
        $john = $this->getTestUser('john@kolab.org');

        $this->assertSame($john->id, $domain->walletOwner()->id);

        // A domain without an owner
        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW | Domain::STATUS_SUSPENDED
                    | Domain::STATUS_LDAP_READY | Domain::STATUS_CONFIRMED,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $this->assertSame(null, $domain->walletOwner());
    }

    /**
     * Test domain verifying
     */
    public function testVerify(): void
    {
        Queue::fake();

        // A domain with DNS records
        $domain = $this->getTestDomain('gmail.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $this->assertTrue($domain->verify());
        $this->assertTrue($domain->isVerified());

        // A domain without DNS records
        $domain = $this->getTestDomain('public-active.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        $this->assertFalse($domain->verify());
        $this->assertFalse($domain->isVerified());
    }
}
