<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        Domain::create([
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
}
