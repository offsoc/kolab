<?php

namespace Tests\Unit;

use App\Domain;
use Tests\TestCase;
use Tests\Utils;

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

        $domains = Utils::powerSet($statuses);

        foreach ($domains as $domainStatuses) {
            $domain = new Domain(
                [
                    'namespace' => 'test.com',
                    'status' => \array_sum($domainStatuses),
                    'type' => Domain::TYPE_EXTERNAL,
                ]
            );

            $domainStatuses = [];

            foreach ($statuses as $status) {
                if ($domain->status & $status) {
                    $domainStatuses[] = $status;
                }
            }

            $this->assertSame($domain->status, \array_sum($domainStatuses));

            // either one is true, but not both
            $this->assertSame(
                $domain->isNew() === in_array(Domain::STATUS_NEW, $domainStatuses),
                $domain->isActive() === in_array(Domain::STATUS_ACTIVE, $domainStatuses)
            );

            $this->assertTrue(
                $domain->isNew() === in_array(Domain::STATUS_NEW, $domainStatuses)
            );

            $this->assertTrue(
                $domain->isActive() === in_array(Domain::STATUS_ACTIVE, $domainStatuses)
            );

            $this->assertTrue(
                $domain->isConfirmed() === in_array(Domain::STATUS_CONFIRMED, $domainStatuses)
            );

            $this->assertTrue(
                $domain->isSuspended() === in_array(Domain::STATUS_SUSPENDED, $domainStatuses)
            );

            $this->assertTrue(
                $domain->isDeleted() === in_array(Domain::STATUS_DELETED, $domainStatuses)
            );

            if (\config('app.with_ldap')) {
                $this->assertTrue(
                    $domain->isLdapReady() === in_array(Domain::STATUS_LDAP_READY, $domainStatuses)
                );
            }

            $this->assertTrue(
                $domain->isVerified() === in_array(Domain::STATUS_VERIFIED, $domainStatuses)
            );
        }
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

        $domains = Utils::powerSet($types);

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

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash_code);

        $hash_text = $domain->hash(Domain::HASH_TEXT);

        $this->assertMatchesRegularExpression('/^kolab-verify=[a-f0-9]{32}$/', $hash_text);

        $this->assertSame($hash_code, str_replace('kolab-verify=', '', $hash_text));

        $hash_cname = $domain->hash(Domain::HASH_CNAME);

        $this->assertSame('kolab-verify', $hash_cname);

        $hash_code2 = $domain->hash(Domain::HASH_CODE);

        $this->assertSame($hash_code, $hash_code2);
    }

    /**
     * Test domain statusText()
     */
    public function testStatusText(): void
    {
        $domain = new Domain();

        $this->assertSame('', $domain->statusText());

        $domain->status = Domain::STATUS_NEW
            | Domain::STATUS_ACTIVE
            | Domain::STATUS_SUSPENDED
            | Domain::STATUS_DELETED
            | Domain::STATUS_CONFIRMED
            | Domain::STATUS_VERIFIED
            | Domain::STATUS_LDAP_READY;

        $expected = [
            'new (1)',
            'suspended (4)',
            'deleted (8)',
            'confirmed (16)',
            'verified (32)',
            'ldapReady (64)',
        ];

        $this->assertSame(implode(', ', $expected), $domain->statusText());
    }

    /**
     * Test setNamespaceAttribute()
     */
    public function testSetNamespaceAttribute(): void
    {
        $domain = new Domain();

        $domain->namespace = 'UPPERCASE';

        $this->assertTrue($domain->namespace === 'uppercase'); // @phpstan-ignore-line
    }

    /**
     * Test setStatusAttribute()
     */
    public function testSetStatusAttribute()
    {
        $domain = new Domain();

        $this->expectException(\Exception::class);

        $domain->status = 123456;

        // Public domain
        $domain = new Domain();
        $domain->type = Domain::TYPE_PUBLIC;

        $domain->status = 115;

        $this->assertTrue($domain->status == 115); // @phpstan-ignore-line

        // ACTIVE makes not NEW
        $domain = new Domain();
        $domain->status = Domain::STATUS_NEW;

        $this->assertTrue($domain->isNew());
        $this->assertFalse($domain->isActive());

        $domain->status |= Domain::STATUS_ACTIVE;

        $this->assertFalse($domain->isNew());
        $this->assertTrue($domain->isActive());

        // CONFIRMED sets VERIFIED.
        $domain = new Domain();
        $domain->status = Domain::STATUS_CONFIRMED;

        $this->assertTrue($domain->isConfirmed());
        $this->assertTrue($domain->isVerified());

        // CONFIRMED sets ACTIVE.
        $domain = new Domain();
        $domain->status = Domain::STATUS_CONFIRMED;

        $this->assertTrue($domain->isConfirmed());
        $this->assertTrue($domain->isActive());

        // DELETED drops ACTIVE
        $domain = new Domain();
        $domain->status = Domain::STATUS_ACTIVE;

        $this->assertTrue($domain->isActive());
        $this->assertFalse($domain->isNew());
        $this->assertFalse($domain->isDeleted());

        $domain->status |= Domain::STATUS_DELETED;

        $this->assertFalse($domain->isActive());
        $this->assertFalse($domain->isNew());
        $this->assertTrue($domain->isDeleted());

        // SUSPENDED drops ACTIVE.
        $domain = new Domain();
        $domain->status = Domain::STATUS_ACTIVE;

        $this->assertTrue($domain->isActive());
        $this->assertFalse($domain->isSuspended());

        $domain->status |= Domain::STATUS_SUSPENDED;

        $this->assertFalse($domain->isActive());
        $this->assertTrue($domain->isSuspended());
    }
}
