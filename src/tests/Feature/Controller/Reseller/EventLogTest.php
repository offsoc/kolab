<?php

namespace Tests\Feature\Controller\Reseller;

use App\EventLog;
use Tests\TestCase;

class EventLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();

        EventLog::query()->delete();
    }

    protected function tearDown(): void
    {
        EventLog::query()->delete();

        parent::tearDown();
    }

    /**
     * Test listing events for a user (GET /api/v4/eventlog/user/{user})
     */
    public function testUserLog(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller1 = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(403);

        // Reseller user (unknown object type)
        $response = $this->actingAs($reseller1)->get("api/v4/eventlog/eeee/{$user->id}");
        $response->assertStatus(404);

        // Reseller user (empty list)
        $response = $this->actingAs($reseller1)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
        $this->assertFalse($json['hasMore']);

        // Non-empty list
        $event1 = EventLog::createFor($user, EventLog::TYPE_SUSPENDED, "Event 1", ['test' => 'test1']);
        $event1->created_at = now();
        $event1->save();
        $event2 = EventLog::createFor($user, EventLog::TYPE_UNSUSPENDED, "Event 2", ['test' => 'test2']);
        $event2->created_at = (clone now())->subDay();
        $event2->save();

        $response = $this->actingAs($reseller1)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
        $this->assertCount(2, $json['list']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($event1->id, $json['list'][0]['id']);
        $this->assertSame($event1->comment, $json['list'][0]['comment']);
        $this->assertSame($event1->data, $json['list'][0]['data']);
        $this->assertSame($reseller1->email, $json['list'][0]['user']);
        $this->assertSame('Suspended', $json['list'][0]['event']);
        $this->assertSame($event2->id, $json['list'][1]['id']);
        $this->assertSame($event2->comment, $json['list'][1]['comment']);
        $this->assertSame($event2->data, $json['list'][1]['data']);
        $this->assertSame($reseller1->email, $json['list'][1]['user']);
        $this->assertSame('Unsuspended', $json['list'][1]['event']);

        // A user in another tenant
        $user = $this->getTestUser('user@sample-tenant.dev-local');
        $event3 = EventLog::createFor($user, EventLog::TYPE_SUSPENDED, "Event 3");

        $response = $this->actingAs($reseller1)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(404);

        $response = $this->actingAs($reseller2)->get("api/v4/eventlog/user/{$user->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame($event3->id, $json['list'][0]['id']);
    }
}
