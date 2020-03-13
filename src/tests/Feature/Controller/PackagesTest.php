<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\PackagesController;
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
        $package_domain = Package::where('title', 'domain-hosting')->first();
        $package_kolab = Package::where('title', 'kolab')->first();
        $package_lite = Package::where('title', 'lite')->first();

        $response = $this->actingAs($user)->get("api/v4/packages");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);

        $this->assertSame($package_domain->id, $json[0]['id']);
        $this->assertSame($package_domain->title, $json[0]['title']);
        $this->assertSame($package_domain->name, $json[0]['name']);
        $this->assertSame($package_domain->description, $json[0]['description']);
        $this->assertSame($package_domain->isDomain(), $json[0]['isDomain']);
        $this->assertSame($package_domain->cost(), $json[0]['cost']);

        $this->assertSame($package_kolab->id, $json[1]['id']);
        $this->assertSame($package_kolab->title, $json[1]['title']);
        $this->assertSame($package_kolab->name, $json[1]['name']);
        $this->assertSame($package_kolab->description, $json[1]['description']);
        $this->assertSame($package_kolab->isDomain(), $json[1]['isDomain']);
        $this->assertSame($package_kolab->cost(), $json[1]['cost']);

        $this->assertSame($package_lite->id, $json[2]['id']);
        $this->assertSame($package_lite->title, $json[2]['title']);
        $this->assertSame($package_lite->name, $json[2]['name']);
        $this->assertSame($package_lite->description, $json[2]['description']);
        $this->assertSame($package_lite->isDomain(), $json[2]['isDomain']);
        $this->assertSame($package_lite->cost(), $json[2]['cost']);
    }
}
