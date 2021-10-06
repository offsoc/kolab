<?php

namespace Tests\Feature\PowerDNS;

use App\PowerDNS\Domain;
use App\PowerDNS\Record;
use Tests\TestCase;

class RecordTest extends TestCase
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
     * Test creating DNS records
     */
    public function testCreateRecord(): void
    {
        $before = $this->domain->getSerial();

        Record::create([
                'domain_id' => $this->domain->id,
                'name' => $this->domain->{'name'},
                'type' => "MX",
                'content' => '10 mx01.' . $this->domain->{'name'} . '.'
        ]);

        $after = $this->domain->getSerial();

        $this->assertTrue($before < $after);
    }

    /**
     * Test updating DNS records
     */
    public function testUpdateRecord(): void
    {
        $record = Record::create([
                'domain_id' => $this->domain->id,
                'name' => $this->domain->{'name'},
                'type' => "MX",
                'content' => '10 mx01.' . $this->domain->{'name'} . '.'
        ]);

        $before = $this->domain->getSerial();

        $record->content = 'test';
        $record->save();

        $after = $this->domain->getSerial();

        $this->assertTrue($before < $after);
    }

    /**
     * Test deleting DNS records
     */
    public function testDeleteRecord(): void
    {
        $record = Record::create([
                'domain_id' => $this->domain->id,
                'name' => $this->domain->{'name'},
                'type' => "MX",
                'content' => '10 mx01.' . $this->domain->{'name'} . '.'
        ]);

        $before = $this->domain->getSerial();

        $record->delete();

        $after = $this->domain->getSerial();

        $this->assertTrue($before < $after);
    }
}
