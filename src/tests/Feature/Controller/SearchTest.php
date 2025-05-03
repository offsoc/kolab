<?php

namespace Tests\Feature\Controller;

use App\Contact;
use Tests\TestCase;

class SearchTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
        Contact::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');
        Contact::truncate();

        parent::tearDown();
    }

    /**
     * Test searching contacts
     */
    public function testSearchContacts(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/search/contacts");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        // Empty list
        $response = $this->actingAs($john)->get("api/v4/search/contacts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);

        // Create two contacts
        $john->contacts()->create(['email' => 'test1@domain.com', 'name' => 'Name1']);
        $john->contacts()->create(['email' => 'test2@domain.com', 'name' => 'Name2']);

        $response = $this->actingAs($john)->get("api/v4/search/contacts?search=test");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(['email' => 'test1@domain.com', 'name' => 'Name1'], $json['list'][0]);
        $this->assertSame(['email' => 'test2@domain.com', 'name' => 'Name2'], $json['list'][1]);

        // Search by part of email address
        $response = $this->actingAs($john)->get("api/v4/search/contacts?search=ST1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(['email' => 'test1@domain.com', 'name' => 'Name1'], $json['list'][0]);

        // Search by part of name
        $response = $this->actingAs($john)->get("api/v4/search/contacts?search=ME2");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(['email' => 'test2@domain.com', 'name' => 'Name2'], $json['list'][0]);

        // Accessing by non-controller user
        $response = $this->actingAs($jack)->get("api/v4/search/contacts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(['email' => 'test1@domain.com', 'name' => 'Name1'], $json['list'][0]);
        $this->assertSame(['email' => 'test2@domain.com', 'name' => 'Name2'], $json['list'][1]);

        // Make sure a user from another account can't see other account contacts
        $jane = $this->getTestUser('jane@kolabnow.com');
        $response = $this->actingAs($jane)->get("api/v4/search/contacts");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertCount(0, $json['list']);
    }

    /**
     * Test searching
     */
    public function testSearchSelf(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/search/self");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        // w/o aliases, w/o search
        $response = $this->actingAs($john)->get("api/v4/search/self");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(['email' => 'john@kolab.org', 'name' => 'John Doe'], $json['list'][0]);

        // with aliases, w/o search
        $response = $this->actingAs($john)->get("api/v4/search/self?alias=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(['email' => 'john.doe@kolab.org', 'name' => 'John Doe'], $json['list'][0]);
        $this->assertSame(['email' => 'john@kolab.org', 'name' => 'John Doe'], $json['list'][1]);

        // with aliases and search
        $response = $this->actingAs($john)->get("api/v4/search/self?alias=1&search=doe@");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame(['email' => 'john.doe@kolab.org', 'name' => 'John Doe'], $json['list'][0]);

        // User no account owner - with aliases, w/o search
        $response = $this->actingAs($jack)->get("api/v4/search/self?alias=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(['email' => 'jack.daniels@kolab.org', 'name' => 'Jack Daniels'], $json['list'][0]);
        $this->assertSame(['email' => 'jack@kolab.org', 'name' => 'Jack Daniels'], $json['list'][1]);
    }

    /**
     * Test searching
     */
    public function testSearchUser(): void
    {
        // FIXME: It looks it's not working when you have APP_WITH_USER_SEARCH=true in .env file
        \putenv('APP_WITH_USER_SEARCH=false'); // can't be done using \config()
        $this->refreshApplication(); // reload routes

        // User search route disabled
        $response = $this->get("api/v4/search/user");
        $response->assertStatus(404);

        \putenv('APP_WITH_USER_SEARCH=true'); // can't be done using \config()
        $this->refreshApplication(); // reload routes

        // Unauth access not allowed
        $response = $this->get("api/v4/search/user");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $jane = $this->getTestUser('jane@kolabnow.com');

        // Account owner - without aliases, w/o search
        $response = $this->actingAs($john)->get("api/v4/search/user");
        $response->assertStatus(200);

        $json = $response->json();

        $users = [
            [
                'email' => 'joe@kolab.org',
                'name' => '',
            ],
            [
                'email' => 'ned@kolab.org',
                'name' => 'Edward Flanders',
            ],
            [
                'email' => 'jack@kolab.org',
                'name' => 'Jack Daniels',
            ],
            [
                'email' => 'john@kolab.org',
                'name' => 'John Doe',
            ],
        ];


        $this->assertSame(count($users), $json['count']);
        $this->assertSame($users, $json['list']);

        // User no account owner, without aliases w/o search
        $response = $this->actingAs($jack)->get("api/v4/search/user");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(count($users), $json['count']);
        $this->assertSame($users, $json['list']);

        // with aliases, w/o search
        $response = $this->actingAs($john)->get("api/v4/search/user?alias=1");
        $response->assertStatus(200);

        $json = $response->json();

        $expected = [
            [
                'email' => 'joe.monster@kolab.org',
                'name' => '',
            ],
            $users[0],
            $users[1],
            [
                'email' => 'jack.daniels@kolab.org',
                'name' => 'Jack Daniels',
            ],
            $users[2],
            [
                'email' => 'john.doe@kolab.org',
                'name' => 'John Doe',
            ],
            $users[3],
        ];

        $this->assertSame(count($expected), $json['count']);
        $this->assertSame($expected, $json['list']);

        // with aliases and search
        $response = $this->actingAs($john)->get("api/v4/search/user?alias=1&search=john");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertSame(['email' => 'john.doe@kolab.org', 'name' => 'John Doe'], $json['list'][0]);
        $this->assertSame(['email' => 'john@kolab.org', 'name' => 'John Doe'], $json['list'][1]);

        // Make sure we can't find users from outside of an account
        $response = $this->actingAs($john)->get("api/v4/search/user?alias=1&search=jane");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
    }
}
