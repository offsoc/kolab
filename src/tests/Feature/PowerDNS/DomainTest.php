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

        $this->domain = Domain::firstOrCreate(
            [
                'name' => 'test-domain.com'
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->domain->delete();

        parent::tearDown();
    }

    public function testDomainCreate(): void
    {
        $this->assertCount(1, $this->domain->records()->where('type', 'SOA')->get());
        $this->assertCount(2, $this->domain->records()->where('type', 'NS')->get());
    }

    public function testCreateRecord(): void
    {
        $before = $this->domain->getSerial();

        Record::create(
            [
                'domain_id' => $this->domain->id,
                'name' => $this->domain->{'name'},
                'type' => "MX",
                'content' => '10 mx01.' . $this->domain->{'name'} . '.'
            ]
        );

        Record::create(
            [
                'domain_id' => $this->domain->id,
                'name' => 'mx01.' . $this->domain->{'name'},
                'type' => "A",
                'content' => '127.0.0.1'
            ]
        );

        $after = $this->domain->getSerial();

        $this->assertTrue($before < $after);
    }
}
