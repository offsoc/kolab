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
    public function setUp(): void
    {
        parent::setUp();

        $domains = [
            'public-active.com',
            'gmail.com',
            'ci-success-cname.kolab.org',
            'ci-success-txt.kolab.org',
            'ci-failure-cname.kolab.org',
            'ci-failure-txt.kolab.org',
            'ci-failure-none.kolab.org',
        ];

        Domain::whereIn('namespace', $domains)->delete();
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

        Queue::assertPushed(\App\Jobs\DomainCreate::class, 1);
        Queue::assertPushed(\App\Jobs\DomainCreate::class, function ($job) use ($domain) {
            $job_domain = TestCase::getObjectProperty($job, 'domain');

            return $job_domain->id === $domain->id
                && $job_domain->namespace === $domain->namespace;
        });
/*
        Queue::assertPushedWithChain(\App\Jobs\DomainCreate::class, [
            \App\Jobs\DomainVerify::class,
        ]);
*/
/*
        FIXME: Looks like we can't really do detailed assertions on chained jobs
               Another thing to consider is if we maybe should run these jobs
               independently (not chained) and make sure there's no race-condition
               in status update

        Queue::assertPushed(\App\Jobs\DomainVerify::class, 1);
        Queue::assertPushed(\App\Jobs\DomainVerify::class, function ($job) use ($domain) {
            $job_domain = TestCase::getObjectProperty($job, 'domain');

            return $job_domain->id === $domain->id
                && $job_domain->namespace === $domain->namespace;
        });
*/
    }

    /**
     * Tests getPublicDomains() method
     */
    public function testGetPublicDomains(): void
    {
        $public_domains = Domain::getPublicDomains();

        $this->assertNotContains('public-active.com', $public_domains);

        Queue::fake();

        $domain = Domain::create([
                'namespace' => 'public-active.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        // Public but non-active domain should not be returned
        $public_domains = Domain::getPublicDomains();
        $this->assertNotContains('public-active.com', $public_domains);

        $domain = Domain::where('namespace', 'public-active.com')->first();
        $domain->status = Domain::STATUS_ACTIVE;
        $domain->save();

        // Public and active domain should be returned
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

        Queue::fake();

        $domain_props = ['status' => Domain::STATUS_NEW, 'type' => Domain::TYPE_EXTERNAL];

        $domain = $this->getTestDomain('ci-failure-none.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertTrue(!$domain->isConfirmed());

        $domain = $this->getTestDomain('ci-failure-txt.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertTrue(!$domain->isConfirmed());

        $domain = $this->getTestDomain('ci-failure-cname.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm() === false);
        $this->assertTrue(!$domain->isConfirmed());

        $domain = $this->getTestDomain('ci-success-txt.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm());
        $this->assertTrue($domain->isConfirmed());

        $domain = $this->getTestDomain('ci-success-cname.kolab.org', $domain_props);

        $this->assertTrue($domain->confirm());
        $this->assertTrue($domain->isConfirmed());
    }
}
