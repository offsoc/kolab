<?php

namespace Tests\Feature\Controller\User;

use App\Delegation;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('deleted@kolabnow.com');
        Delegation::query()->delete();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('deleted@kolabnow.com');
        Delegation::query()->delete();

        parent::tearDown();
    }

    /**
     * Test delegation creation (POST /api/v4/users/<id>/delegations)
     */
    public function testCreateDelegation(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $joe = $this->getTestUser('joe@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        // Test unauth access
        $response = $this->post("api/v4/users/{$john->id}/delegations", []);
        $response->assertStatus(401);

        // Test access to other user/account
        $response = $this->actingAs($jack)->post("api/v4/users/{$john->id}/delegations", []);
        $response->assertStatus(403);

        // Test request made by the delegator user
        $response = $this->actingAs($john)->post("api/v4/users/{$john->id}/delegations", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The email field is required."], $json['errors']['email']);
        $this->assertSame(["The options field is required."], $json['errors']['options']);

        // Delegatee in another domain (and account) and invalid options
        $post = ['email' => 'fred@' . \config('app.domain'), 'options' => ['mail' => 're']];
        $response = $this->actingAs($john)->post("api/v4/users/{$john->id}/delegations", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The specified email address is not a valid delegation target."], $json['errors']['email']);

        // Invalid options
        $post = ['email' => $joe->email, 'options' => ['ufo' => 're']];
        $response = $this->actingAs($john)->post("api/v4/users/{$john->id}/delegations", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The specified delegation options are invalid."], $json['errors']['options']);

        // Valid input
        $post = ['email' => $joe->email, 'options' => ['mail' => 'read-only']];
        $response = $this->actingAs($john)->post("api/v4/users/{$john->id}/delegations", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Delegation created successfully.", $json['message']);

        $delegatee = $john->delegatees()->first();
        $this->assertSame($joe->email, $delegatee->email);
        $this->assertSame(['mail' => 'read-only'], $delegatee->delegation->options);

        // TODO: Action taken by another wallet controller
    }

    /**
     * Test listing delegations (GET /api/v4/users/<id>/delegations)
     */
    public function testDelegations(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $joe = $this->getTestUser('joe@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');
        $user = $this->getTestUser('deleted@kolabnow.com');

        Delegation::create(['user_id' => $john->id, 'delegatee_id' => $jack->id]);
        Delegation::create(['user_id' => $john->id, 'delegatee_id' => $joe->id, 'options' => ['mail' => 'r']]);

        // Test unauth access
        $response = $this->get("api/v4/users/{$john->id}/delegations");
        $response->assertStatus(401);

        // Test access to other user/account
        $response = $this->actingAs($user)->get("api/v4/users/{$john->id}/delegations");
        $response->assertStatus(403);

        // Test that non-controller cannot access
        $response = $this->actingAs($jack)->get("api/v4/users/{$john->id}/delegations");
        $response->assertStatus(403);

        // Test request made by the delegator user
        $response = $this->actingAs($john)->get("api/v4/users/{$john->id}/delegations");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json['list']);
        $this->assertSame(2, $json['count']);
        $this->assertSame($jack->email, $json['list'][0]['email']);
        $this->assertSame([], $json['list'][0]['options']);
        $this->assertSame($joe->email, $json['list'][1]['email']);
        $this->assertSame(['mail' => 'r'], $json['list'][1]['options']);

        // Test request made by the delegators wallet controller
        $response = $this->actingAs($ned)->get("api/v4/users/{$john->id}/delegations");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json['list']);
    }

    /**
     * Test listing delegators (GET /api/v4/users/<id>/delegators)
     */
    public function testDelegators(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        Delegation::create(['user_id' => $john->id, 'delegatee_id' => $jack->id, 'options' => ['mail' => 'r']]);

        // Test unauth access
        $response = $this->get("api/v4/users/{$john->id}/delegators");
        $response->assertStatus(401);

        // Test that non-controller cannot access other user
        $response = $this->actingAs($jack)->get("api/v4/users/{$john->id}/delegators");
        $response->assertStatus(403);

        // Test request made by the delegatee user
        $response = $this->actingAs($jack)->get("api/v4/users/{$jack->id}/delegators");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json['list']);
        $this->assertSame(1, $json['count']);
        $this->assertSame($john->email, $json['list'][0]['email']);
        $this->assertSame('John Doe', $json['list'][0]['name']);
        $this->assertSame(['john.doe@kolab.org'], $json['list'][0]['aliases']);

        // Test request made by the delegatee's owner
        $response = $this->actingAs($john)->get("api/v4/users/{$jack->id}/delegators");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(1, $json['list']);
        $this->assertSame(1, $json['count']);
        $this->assertSame($john->email, $json['list'][0]['email']);
        $this->assertSame('John Doe', $json['list'][0]['name']);
        $this->assertSame(['john.doe@kolab.org'], $json['list'][0]['aliases']);
    }

    /**
     * Test delegatee deleting (DELETE /api/v4/users/<id>/delegations/<email>)
     */
    public function testDeleteDelegation(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $user = $this->getTestUser('deleted@kolabnow.com');

        Delegation::create(['user_id' => $john->id, 'delegatee_id' => $jack->id]);

        // Test unauth access
        $response = $this->delete("api/v4/users/{$john->id}/delegations/{$jack->email}");
        $response->assertStatus(401);

        // Test access to other user/account
        $response = $this->actingAs($user)->delete("api/v4/users/{$john->id}/delegations/{$jack->email}");
        $response->assertStatus(403);

        // Test that non-controller cannot remove himself
        $response = $this->actingAs($jack)->delete("api/v4/users/{$john->id}/delegations/{$jack->email}");
        $response->assertStatus(403);

        // Test non-existing delegation
        $response = $this->actingAs($john)->delete("api/v4/users/{$john->id}/delegations/unknown@kolabnow.com");
        $response->assertStatus(404);

        // Test successful delegation removal
        $response = $this->actingAs($john)->delete("api/v4/users/{$john->id}/delegations/{$jack->email}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame('Delegation deleted successfully.', $json['message']);
    }
}
