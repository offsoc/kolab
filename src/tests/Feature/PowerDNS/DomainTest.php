<?php

namespace Tests\Feature\PowerDNS;

use App\PowerDNS\Domain;
use App\PowerDNS\Record;
use Tests\TestCase;

class DomainTest extends TestCase
{
    /** @var \App\PowerDNS\Domain $domain */
    private $domain = null;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->domain = Domain::firstOrCreate(['name' => 'test-domain.com']);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->domain->delete();

        parent::tearDown();
    }

    /**
     * Test domain record creation (observer)
     */
    public function testDomainCreate(): void
    {
        $this->assertCount(1, $this->domain->records()->where('type', 'SOA')->get());
        $this->assertCount(2, $this->domain->records()->where('type', 'NS')->get());
        $this->assertCount(3, $this->domain->records()->get());

        $this->assertCount(1, $this->domain->settings()->get());

        // TODO: Test content of every domain record/setting
    }
}
