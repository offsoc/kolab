<?php

namespace Tests\Feature\Controller;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use App\Wallet;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('test1@domainscontroller.com');
        $this->deleteTestDomain('domainscontroller.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('test1@domainscontroller.com');
        $this->deleteTestDomain('domainscontroller.com');

        parent::tearDown();
    }

    /**
     * Test domain confirm request
     */
    public function testConfirm(): void
    {
        $sku_domain = Sku::where('title', 'domain-hosting')->first();
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        Entitlement::create([
                'wallet_id' => $user->wallets()->first()->id,
                'sku_id' => $sku_domain->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
        ]);

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertEquals('error', $json['status']);
        $this->assertEquals('Domain ownership verification failed.', $json['message']);

        $domain->status |= Domain::STATUS_CONFIRMED;
        $domain->save();

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('Domain verified successfully.', $json['message']);
        $this->assertTrue(is_array($json['statusInfo']));

        // Not authorized access
        $response = $this->actingAs($john)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(403);

        // Authorized access by additional account controller
        $domain = $this->getTestDomain('kolab.org');
        $response = $this->actingAs($ned)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(200);
    }

    /**
     * Test fetching domains list
     */
    public function testIndex(): void
    {
        // User with no domains
        $user = $this->getTestUser('test1@domainscontroller.com');
        $response = $this->actingAs($user)->get("api/v4/domains");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame([], $json);

        // User with custom domain(s)
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $response = $this->actingAs($john)->get("api/v4/domains");
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertCount(1, $json);
        $this->assertSame('kolab.org', $json[0]['namespace']);
        // Values below are tested by Unit tests
        $this->assertArrayHasKey('isConfirmed', $json[0]);
        $this->assertArrayHasKey('isDeleted', $json[0]);
        $this->assertArrayHasKey('isVerified', $json[0]);
        $this->assertArrayHasKey('isSuspended', $json[0]);
        $this->assertArrayHasKey('isActive', $json[0]);
        $this->assertArrayHasKey('isLdapReady', $json[0]);

        $response = $this->actingAs($ned)->get("api/v4/domains");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('kolab.org', $json[0]['namespace']);
    }

    /**
     * Test fetching domain info
     */
    public function testShow(): void
    {
        $sku_domain = Sku::where('title', 'domain-hosting')->first();
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        Entitlement::create([
                'wallet_id' => $user->wallets()->first()->id,
                'sku_id' => $sku_domain->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
        ]);

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($domain->id, $json['id']);
        $this->assertEquals($domain->namespace, $json['namespace']);
        $this->assertEquals($domain->status, $json['status']);
        $this->assertEquals($domain->type, $json['type']);
        $this->assertSame($domain->hash(Domain::HASH_TEXT), $json['hash_text']);
        $this->assertSame($domain->hash(Domain::HASH_CNAME), $json['hash_cname']);
        $this->assertSame($domain->hash(Domain::HASH_CODE), $json['hash_code']);
        $this->assertCount(4, $json['config']);
        $this->assertTrue(strpos(implode("\n", $json['config']), $domain->namespace) !== false);
        $this->assertCount(8, $json['dns']);
        $this->assertTrue(strpos(implode("\n", $json['dns']), $domain->namespace) !== false);
        $this->assertTrue(strpos(implode("\n", $json['dns']), $domain->hash()) !== false);
        $this->assertTrue(is_array($json['statusInfo']));
        // Values below are tested by Unit tests
        $this->assertArrayHasKey('isConfirmed', $json);
        $this->assertArrayHasKey('isDeleted', $json);
        $this->assertArrayHasKey('isVerified', $json);
        $this->assertArrayHasKey('isSuspended', $json);
        $this->assertArrayHasKey('isActive', $json);
        $this->assertArrayHasKey('isLdapReady', $json);

        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        // Not authorized - Other account domain
        $response = $this->actingAs($john)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);

        $domain = $this->getTestDomain('kolab.org');

        // Ned is an additional controller on kolab.org's wallet
        $response = $this->actingAs($ned)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(200);

        // Jack has no entitlement/control over kolab.org
        $response = $this->actingAs($jack)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);
    }

    /**
     * Test fetching domain status (GET /api/v4/domains/<domain-id>/status)
     * and forcing setup process update (?refresh=1)
     *
     * @group dns
     */
    public function testStatus(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $domain = $this->getTestDomain('kolab.org');

        // Test unauthorized access
        $response = $this->actingAs($jack)->get("/api/v4/domains/{$domain->id}/status");
        $response->assertStatus(403);

        $domain->status = Domain::STATUS_NEW | Domain::STATUS_ACTIVE | Domain::STATUS_LDAP_READY;
        $domain->save();

        // Get domain status
        $response = $this->actingAs($john)->get("/api/v4/domains/{$domain->id}/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse($json['isVerified']);
        $this->assertFalse($json['isReady']);
        $this->assertCount(4, $json['process']);
        $this->assertSame('domain-verified', $json['process'][2]['label']);
        $this->assertSame(false, $json['process'][2]['state']);
        $this->assertTrue(empty($json['status']));
        $this->assertTrue(empty($json['message']));

        // Now "reboot" the process and verify the domain
        $response = $this->actingAs($john)->get("/api/v4/domains/{$domain->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isVerified']);
        $this->assertTrue($json['isReady']);
        $this->assertCount(4, $json['process']);
        $this->assertSame('domain-verified', $json['process'][2]['label']);
        $this->assertSame(true, $json['process'][2]['state']);
        $this->assertSame('domain-confirmed', $json['process'][3]['label']);
        $this->assertSame(true, $json['process'][3]['state']);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Setup process finished successfully.', $json['message']);

        // TODO: Test completing all process steps
    }
}
