<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach ($this->domains as $domain) {
            $this->deleteTestDomain($domain);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        foreach ($this->domains as $domain) {
            $this->deleteTestDomain($domain);
        }

        parent::tearDown();
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
        $this->assertSame(Domain::STATUS_NEW | Domain::STATUS_ACTIVE, $result->status);
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

        $job = new \App\Jobs\Domain\CreateJob($domain->id);
        $job->handle();
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

        $domain = Domain::where('namespace', 'public-active.com')->first();
        $domain->type = Domain::TYPE_PUBLIC;
        $domain->save();

        $public_domains = Domain::getPublicDomains();
        $this->assertContains('public-active.com', $public_domains);
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

        // Delete the domain for real
        $job = new \App\Jobs\Domain\DeleteJob($domain->id);
        $job->handle();

        $this->assertTrue(Domain::withTrashed()->where('id', $domain->id)->first()->isDeleted());

        $domain->forceDelete();

        $this->assertCount(0, Domain::withTrashed()->where('id', $domain->id)->get());
    }
}
