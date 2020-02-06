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

        Domain::where('namespace', 'public-active.com')
            ->orWhere('namespace', 'gmail.com')->delete();
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

        Queue::assertPushed(\App\Jobs\ProcessDomainCreate::class, 1);
        Queue::assertPushed(\App\Jobs\ProcessDomainCreate::class, function ($job) use ($domain) {
            $job_domain = TestCase::getObjectProperty($job, 'domain');

            return $job_domain->id === $domain->id
                && $job_domain->namespace === $domain->namespace;
        });

        Queue::assertPushedWithChain(\App\Jobs\ProcessDomainCreate::class, [
            \App\Jobs\ProcessDomainVerify::class,
        ]);

/*
        FIXME: Looks like we can't really do detailed assertions on chained jobs
               Another thing to consider is if we maybe should run these jobs
               independently (not chained) and make sure there's no race-condition
               in status update

        Queue::assertPushed(\App\Jobs\ProcessDomainVerify::class, 1);
        Queue::assertPushed(\App\Jobs\ProcessDomainVerify::class, function ($job) use ($domain) {
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
     * Test domain confirmation
     *
     * @group dns
     */
    public function testConfirm(): void
    {
        // TODO
        $this->markTestIncomplete();
    }

    /**
     * Test domain verification
     *
     * @group dns
     */
    public function testVerify(): void
    {
        // TODO
        $this->markTestIncomplete();
    }
}
