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

        Domain::where('namespace', 'public-active.com')->delete();
    }

    /**
     * Tests getPublicDomains() method
     */
    public function testGetPublicDomains(): void
    {
        $public_domains = Domain::getPublicDomains();

        $this->assertNotContains('public-active.com', $public_domains);

        // Fake the queue, assert that no jobs were pushed...
        Queue::fake();
        Queue::assertNothingPushed();

        $domain = Domain::create([
                'namespace' => 'public-active.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_PUBLIC,
        ]);

        // Public but non-active domain should not be returned
        $public_domains = Domain::getPublicDomains();
        $this->assertNotContains('public-active.com', $public_domains);

        Queue::assertPushed(\App\Jobs\ProcessDomainCreate::class, 1);
        Queue::assertPushed(\App\Jobs\ProcessDomainCreate::class, function ($job) use ($domain) {
            $job_domain = TestCase::getObjectProperty($job, 'domain');

            return $job_domain->id === $domain->id
                && $job_domain->namespace === $domain->namespace;
        });

        $domain = Domain::where('namespace', 'public-active.com')->first();
        $domain->status = Domain::STATUS_ACTIVE;
        $domain->save();

        // Public and active domain should be returned
        $public_domains = Domain::getPublicDomains();
        $this->assertContains('public-active.com', $public_domains);
    }
}
