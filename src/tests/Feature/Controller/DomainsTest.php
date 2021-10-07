<?php

namespace Tests\Feature\Controller;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use App\Wallet;
use Illuminate\Support\Facades\Queue;
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

        $domain = $this->getTestDomain('kolab.org');
        $domain->settings()->whereIn('key', ['spf_whitelist'])->delete();

        parent::tearDown();
    }

    /**
     * Test domain confirm request
     */
    public function testConfirm(): void
    {
        $sku_domain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
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
     * Test domain config update (POST /api/v4/domains/<domain>/config)
     */
    public function testSetConfig(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $domain = $this->getTestDomain('kolab.org');
        $domain->setSetting('spf_whitelist', null);

        // Test unknown domain id
        $post = ['spf_whitelist' => []];
        $response = $this->actingAs($john)->post("/api/v4/domains/123/config", $post);
        $json = $response->json();

        $response->assertStatus(404);

        // Test access by user not being a wallet controller
        $post = ['spf_whitelist' => []];
        $response = $this->actingAs($jack)->post("/api/v4/domains/{$domain->id}/config", $post);
        $json = $response->json();

        $response->assertStatus(403);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['grey' => 1];
        $response = $this->actingAs($john)->post("/api/v4/domains/{$domain->id}/config", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertCount(1, $json['errors']);
        $this->assertSame('The requested configuration parameter is not supported.', $json['errors']['grey']);

        $this->assertNull($domain->fresh()->getSetting('spf_whitelist'));

        // Test some valid data
        $post = ['spf_whitelist' => ['.test.domain.com']];
        $response = $this->actingAs($john)->post("/api/v4/domains/{$domain->id}/config", $post);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Domain settings updated successfully.', $json['message']);

        $expected = \json_encode($post['spf_whitelist']);
        $this->assertSame($expected, $domain->fresh()->getSetting('spf_whitelist'));

        // Test input validation
        $post = ['spf_whitelist' => ['aaa']];
        $response = $this->actingAs($john)->post("/api/v4/domains/{$domain->id}/config", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame(
            'The entry format is invalid. Expected a domain name starting with a dot.',
            $json['errors']['spf_whitelist'][0]
        );

        $this->assertSame($expected, $domain->fresh()->getSetting('spf_whitelist'));
    }

    /**
     * Test fetching domain info
     */
    public function testShow(): void
    {
        $sku_domain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        $discount = \App\Discount::withEnvTenantContext()->where('code', 'TEST')->first();
        $wallet = $user->wallet();
        $wallet->discount()->associate($discount);
        $wallet->save();

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
        $this->assertSame([], $json['config']['spf_whitelist']);
        $this->assertCount(4, $json['mx']);
        $this->assertTrue(strpos(implode("\n", $json['mx']), $domain->namespace) !== false);
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
        $this->assertCount(1, $json['skus']);
        $this->assertSame(1, $json['skus'][$sku_domain->id]['count']);
        $this->assertSame([0], $json['skus'][$sku_domain->id]['costs']);
        $this->assertSame($wallet->id, $json['wallet']['id']);
        $this->assertSame($wallet->balance, $json['wallet']['balance']);
        $this->assertSame($wallet->currency, $json['wallet']['currency']);
        $this->assertSame($discount->discount, $json['wallet']['discount']);
        $this->assertSame($discount->description, $json['wallet']['discount_description']);

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

    /**
     * Test domain creation (POST /api/v4/domains)
     */
    public function testStore(): void
    {
        Queue::fake();

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');

        // Test empty request
        $response = $this->actingAs($john)->post("/api/v4/domains", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The namespace field is required.", $json['errors']['namespace'][0]);
        $this->assertCount(1, $json['errors']);
        $this->assertCount(1, $json['errors']['namespace']);
        $this->assertCount(2, $json);

        // Test access by user not being a wallet controller
        $post = ['namespace' => 'domainscontroller.com'];
        $response = $this->actingAs($jack)->post("/api/v4/domains", $post);
        $json = $response->json();

        $response->assertStatus(403);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['namespace' => '--'];
        $response = $this->actingAs($john)->post("/api/v4/domains", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The specified domain is invalid.', $json['errors']['namespace'][0]);
        $this->assertCount(1, $json['errors']);
        $this->assertCount(1, $json['errors']['namespace']);

        // Test an existing domain
        $post = ['namespace' => 'kolab.org'];
        $response = $this->actingAs($john)->post("/api/v4/domains", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame('The specified domain is not available.', $json['errors']['namespace']);

        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();

        // Missing package
        $post = ['namespace' => 'domainscontroller.com'];
        $response = $this->actingAs($john)->post("/api/v4/domains", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Package is required.", $json['errors']['package']);
        $this->assertCount(2, $json);

        // Invalid package
        $post['package'] = $package_kolab->id;
        $response = $this->actingAs($john)->post("/api/v4/domains", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Invalid package selected.", $json['errors']['package']);
        $this->assertCount(2, $json);

        // Test full and valid data
        $post['package'] = $package_domain->id;
        $response = $this->actingAs($john)->post("/api/v4/domains", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Domain created successfully.", $json['message']);
        $this->assertCount(2, $json);

        $domain = Domain::where('namespace', $post['namespace'])->first();
        $this->assertInstanceOf(Domain::class, $domain);

        // Assert the new domain entitlements
        $this->assertEntitlements($domain, ['domain-hosting']);

        // Assert the wallet to which the new domain should be assigned to
        $wallet = $domain->wallet();
        $this->assertSame($john->wallets->first()->id, $wallet->id);

        // Test acting as account controller (not owner)

        $this->markTestIncomplete();
    }
}
