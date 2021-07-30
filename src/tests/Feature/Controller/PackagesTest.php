<?php

namespace Tests\Feature\Controller;

use App\Package;
use Tests\TestCase;

class PackagesTest extends TestCase
{
    /**
     * Test fetching packages list
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/packages");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');

        $packageDomain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $packageKolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $packageLite = Package::withEnvTenantContext()->where('title', 'lite')->first();

        $response = $this->actingAs($user)->get("api/v4/packages");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);

        $this->assertSame($packageDomain->id, $json[0]['id']);
        $this->assertSame($packageDomain->title, $json[0]['title']);
        $this->assertSame($packageDomain->name, $json[0]['name']);
        $this->assertSame($packageDomain->description, $json[0]['description']);
        $this->assertSame($packageDomain->isDomain(), $json[0]['isDomain']);
        $this->assertSame($packageDomain->cost(), $json[0]['cost']);

        $this->assertSame($packageKolab->id, $json[1]['id']);
        $this->assertSame($packageKolab->title, $json[1]['title']);
        $this->assertSame($packageKolab->name, $json[1]['name']);
        $this->assertSame($packageKolab->description, $json[1]['description']);
        $this->assertSame($packageKolab->isDomain(), $json[1]['isDomain']);
        $this->assertSame($packageKolab->cost(), $json[1]['cost']);

        $this->assertSame($packageLite->id, $json[2]['id']);
        $this->assertSame($packageLite->title, $json[2]['title']);
        $this->assertSame($packageLite->name, $json[2]['name']);
        $this->assertSame($packageLite->description, $json[2]['description']);
        $this->assertSame($packageLite->isDomain(), $json[2]['isDomain']);
        $this->assertSame($packageLite->cost(), $json[2]['cost']);
    }
}
