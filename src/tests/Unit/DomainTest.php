<?php

namespace Tests\Unit;

use App\Domain;

use Tests\TestCase;

class DomainTest extends TestCase
{
    /**
     * Test basic Domain funtionality
     */
    public function testDomainStatus()
    {
        $statuses = [
            Domain::STATUS_NEW,
            Domain::STATUS_ACTIVE,
            Domain::STATUS_CONFIRMED,
            Domain::STATUS_SUSPENDED,
            Domain::STATUS_DELETED,
        ];

        $domains = \App\Utils::powerSet($statuses);

        foreach ($domains as $domain_statuses) {
            $domain = new Domain(
                [
                    'namespace' => 'test.com',
                    'status' => \array_sum($domain_statuses),
                    'type' => Domain::TYPE_EXTERNAL
                ]
            );

            $this->assertTrue($domain->isNew() === in_array(Domain::STATUS_NEW, $domain_statuses));
            $this->assertTrue($domain->isActive() === in_array(Domain::STATUS_ACTIVE, $domain_statuses));
            $this->assertTrue($domain->isConfirmed() === in_array(Domain::STATUS_CONFIRMED, $domain_statuses));
            $this->assertTrue($domain->isSuspended() === in_array(Domain::STATUS_SUSPENDED, $domain_statuses));
            $this->assertTrue($domain->isDeleted() === in_array(Domain::STATUS_DELETED, $domain_statuses));
        }
    }

    /**
     * Test basic Domain funtionality
     */
    public function testDomainType()
    {
        $types = [
            Domain::TYPE_PUBLIC,
            Domain::TYPE_HOSTED,
            Domain::TYPE_EXTERNAL,
        ];

        $domains = \App\Utils::powerSet($types);

        foreach ($domains as $domain_types) {
            $domain = new Domain(
                [
                    'namespace' => 'test.com',
                    'status' => Domain::STATUS_NEW,
                    'type' => \array_sum($domain_types),
                ]
            );

            $this->assertTrue($domain->isPublic() === in_array(Domain::TYPE_PUBLIC, $domain_types));
            $this->assertTrue($domain->isHosted() === in_array(Domain::TYPE_HOSTED, $domain_types));
            $this->assertTrue($domain->isExternal() === in_array(Domain::TYPE_EXTERNAL, $domain_types));
        }
    }
}
