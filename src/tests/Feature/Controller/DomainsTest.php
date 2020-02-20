<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\DomainsController;
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
        $sku_domain = Sku::where('title', 'domain')->first();
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        // No entitlement (user has no access to this domain), expect 403
        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(403);

        Entitlement::create([
                'owner_id' => $user->id,
                'wallet_id' => $user->wallets()->first()->id,
                'sku_id' => $sku_domain->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
        ]);

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('error', $json['status']);

        $domain->status |= Domain::STATUS_CONFIRMED;
        $domain->save();

        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}/confirm");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals('Domain verified successfully', $json['message']);
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
        $user = $this->getTestUser('john@kolab.org');

        $response = $this->actingAs($user)->get("api/v4/domains");
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
        $sku_domain = Sku::where('title', 'domain')->first();
        $user = $this->getTestUser('test1@domainscontroller.com');
        $domain = $this->getTestDomain('domainscontroller.com', [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
        ]);

        // No entitlement (user has no access to this domain), expect 403
        $response = $this->actingAs($user)->get("api/v4/domains/{$domain->id}");
        $response->assertStatus(403);

        Entitlement::create([
                'owner_id' => $user->id,
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
        $this->assertTrue($json['confirmed'] === false);
        $this->assertSame($domain->hash(Domain::HASH_TEXT), $json['hash_text']);
        $this->assertSame($domain->hash(Domain::HASH_CNAME), $json['hash_cname']);
        $this->assertSame($domain->hash(Domain::HASH_CODE), $json['hash_code']);
        $this->assertCount(4, $json['config']);
        $this->assertTrue(strpos(implode("\n", $json['config']), $domain->namespace) !== false);
        $this->assertCount(8, $json['dns']);
        $this->assertTrue(strpos(implode("\n", $json['dns']), $domain->namespace) !== false);
        $this->assertTrue(strpos(implode("\n", $json['dns']), $domain->hash()) !== false);
    }
}
