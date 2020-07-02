<?php

namespace Tests\Unit;

use App\Domain;
use Tests\TestCase;

class DomainTest extends TestCase
{
    /**
     * Test basic Domain funtionality
     */
    public function testDomainStatus(): void
    {
        $statuses = [
            Domain::STATUS_NEW,
            Domain::STATUS_ACTIVE,
            Domain::STATUS_CONFIRMED,
            Domain::STATUS_SUSPENDED,
            Domain::STATUS_DELETED,
            Domain::STATUS_LDAP_READY,
            Domain::STATUS_VERIFIED,
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
            $this->assertTrue($domain->isLdapReady() === in_array(Domain::STATUS_LDAP_READY, $domain_statuses));
            $this->assertTrue($domain->isVerified() === in_array(Domain::STATUS_VERIFIED, $domain_statuses));
        }
    }

    /**
     * Test setStatusAttribute exception
     */
    public function testDomainStatusInvalid(): void
    {
        $this->expectException(\Exception::class);

        $domain = new Domain(
            [
                'namespace' => 'test.com',
                'status' => 1234567,
            ]
        );
    }

    /**
     * Test basic Domain funtionality
     */
    public function testDomainType(): void
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

    /**
     * Test domain hash generation
     */
    public function testHash(): void
    {
        $domain = new Domain([
            'namespace' => 'test.com',
            'status' => Domain::STATUS_NEW,
        ]);

        $hash_code = $domain->hash();

        $this->assertRegExp('/^[a-f0-9]{32}$/', $hash_code);

        $hash_text = $domain->hash(Domain::HASH_TEXT);

        $this->assertRegExp('/^kolab-verify=[a-f0-9]{32}$/', $hash_text);

        $this->assertSame($hash_code, str_replace('kolab-verify=', '', $hash_text));

        $hash_cname = $domain->hash(Domain::HASH_CNAME);

        $this->assertSame('kolab-verify', $hash_cname);

        $hash_code2 = $domain->hash(Domain::HASH_CODE);

        $this->assertSame($hash_code, $hash_code2);
    }
}
