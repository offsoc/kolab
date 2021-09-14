<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\OpenViduController;
use App\OpenVidu\Room;
use Tests\TestCase;

class OpenViduTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clearMeetEntitlements();
        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null, 'nomedia' => null]);
    }

    public function tearDown(): void
    {
        $this->clearMeetEntitlements();
        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null, 'nomedia' => null]);

        parent::tearDown();
    }

    /**
     * Test listing user rooms
     *
     * @group openvidu
     */
    public function testIndex(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        Room::where('user_id', $jack->id)->delete();

        // Unauth access not allowed
        $response = $this->get("api/v4/openvidu/rooms");
        $response->assertStatus(401);

        // John has one room
        $response = $this->actingAs($john)->get("api/v4/openvidu/rooms");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('john', $json['list'][0]['name']);

        // Jack has no room, but it will be auto-created
        $response = $this->actingAs($jack)->get("api/v4/openvidu/rooms");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertMatchesRegularExpression('/^[0-9a-z-]{11}$/', $json['list'][0]['name']);
    }

    /**
     * Test joining the room
     *
     * @group openvidu
     */
    public function testJoinRoom(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();
        $room->session_id = null;
        $room->save();

        $this->assignMeetEntitlement($john);

        // Unauth access, no session yet
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(323, $json['code']);

        // Non-existing room name
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/non-existing");
        $response->assertStatus(404);

        // TODO: Test accessing an existing room of deleted owner

        // Non-owner, no session yet
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(323, $json['code']);

        // Room owner, no session yet
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(324, $json['code']);

        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $session_id = $room->fresh()->session_id;

        $this->assertSame(Room::ROLE_SUBSCRIBER | Room::ROLE_MODERATOR | Room::ROLE_OWNER, $json['role']);
        $this->assertMatchesRegularExpression('|^wss?://|', $json['token']);
        $this->assertMatchesRegularExpression('|&roomId=' . $session_id . '|', $json['token']);

        $john_token = $json['token'];

        // Non-owner, now the session exists, no 'init' argument
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame(322, $json['code']);
        $this->assertTrue(empty($json['token']));

        // Non-owner, now the session exists, with 'init', but no 'canPublish' argument
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_SUBSCRIBER, $json['role']);
        $this->assertMatchesRegularExpression('|^wss?://|', $json['token']);
        $this->assertMatchesRegularExpression('|&roomId=' . $session_id . '|', $json['token']);
        $this->assertTrue($json['token'] != $john_token);

        // Non-owner, now the session exists, with 'init', and with 'role=PUBLISHER'
        $post = ['canPublish' => true, 'init' => 1];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertMatchesRegularExpression('|^wss?://|', $json['token']);
        $this->assertMatchesRegularExpression('|&roomId=' . $session_id . '|', $json['token']);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertEmpty($json['config']['password']);
        $this->assertEmpty($json['config']['requires_password']);

        // Non-owner, password protected room, password not provided
        $room->setSettings(['password' => 'pass']);
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame(325, $json['code']);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to join the session. Invalid password.', $json['message']);
        $this->assertEmpty($json['config']['password']);
        $this->assertTrue($json['config']['requires_password']);

        // Non-owner, password protected room, invalid provided
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['password' => 'aa']);
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(325, $json['code']);

        // Non-owner, password protected room, valid password provided
        // TODO: Test without init=1
        $post = ['password' => 'pass', 'init' => 'init'];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        // Make sure the room owner can access the password protected room w/o password
        // TODO: Test without init=1
        $post = ['init' => 'init'];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        // Test 'nomedia' room option
        $room->setSettings(['nomedia' => 'true', 'password' => null]);

        $post = ['init' => 'init', 'canPublish' => true];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame(Room::ROLE_PUBLISHER & $json['role'], Room::ROLE_PUBLISHER);

        $post = ['init' => 'init', 'canPublish' => true];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertSame(Room::ROLE_PUBLISHER & $json['role'], 0);
    }

    /**
     * Test locked room and join requests
     *
     * @group openvidu
     */
    public function testJoinRequests(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();
        $room->session_id = null;
        $room->save();
        $room->setSettings(['password' => null, 'locked' => 'true']);

        $this->assignMeetEntitlement($john);

        // Create the session (also makes sure the owner can access a locked room)
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        // Non-owner, locked room, invalid/missing input
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame(326, $json['code']);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to join the session. Room locked.', $json['message']);
        $this->assertTrue($json['config']['locked']);

        // Non-owner, locked room, invalid requestId
        $post = ['nickname' => 'name', 'requestId' => '-----', 'init' => 1];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(326, $json['code']);

        // Non-owner, locked room, invalid requestId
        $post = ['nickname' => 'name', 'init' => 1];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(326, $json['code']);

        // Non-owner, locked room, valid input
        $reqId = '12345678';
        $post = ['nickname' => 'name', 'requestId' => $reqId, 'picture' => 'data:image/png;base64,01234'];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame(327, $json['code']);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to join the session. Room locked.', $json['message']);
        $this->assertTrue($json['config']['locked']);

        $room->refresh();

        $request = $room->requestGet($reqId);

        $this->assertSame($post['nickname'], $request['nickname']);
        $this->assertSame($post['requestId'], $request['requestId']);

        $room->requestAccept($reqId);

        // Non-owner, locked room, join request accepted
        $post['init'] = 1;
        $post['canPublish'] = true;
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertMatchesRegularExpression('|^wss?://|', $json['token']);

        // TODO: Test a scenario where both password and lock are enabled
        // TODO: Test accepting/denying as a non-owner moderator
        // TODO: Test somehow websocket communication
        $this->markTestIncomplete();
    }

    /**
     * Test joining the room
     *
     * @group openvidu
     * @depends testJoinRoom
     */
    public function testJoinRoomGuest(): void
    {
        $this->assignMeetEntitlement('john@kolab.org');

        // There's no easy way to logout the user in the same test after
        // using actingAs(). That's why this is moved to a separate test
        $room = Room::where('name', 'john')->first();

        // Guest, request with screenShare token
        $post = ['canPublish' => true, 'screenShare' => 1, 'init' => 1];
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertMatchesRegularExpression('|^wss?://|', $json['token']);
    }

    /**
     * Test configuring the room (session)
     *
     * @group openvidu
     */
    public function testSetRoomConfig(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();

        // Unauth access not allowed
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}/config", []);
        $response->assertStatus(401);

        // Non-existing room name
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/non-existing/config", []);
        $response->assertStatus(404);

        // TODO: Test a room with a deleted owner

        // Non-owner
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}/config", []);
        $response->assertStatus(403);

        // Room owner
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/config", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Room configuration updated successfully.", $json['message']);

        // Set password and room lock
        $post = ['password' => 'aaa', 'locked' => 1];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/config", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Room configuration updated successfully.", $json['message']);
        $room->refresh();
        $this->assertSame('aaa', $room->getSetting('password'));
        $this->assertSame('true', $room->getSetting('locked'));

        // Unset password and room lock
        $post = ['password' => '', 'locked' => 0];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/config", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Room configuration updated successfully.", $json['message']);
        $room->refresh();
        $this->assertSame(null, $room->getSetting('password'));
        $this->assertSame(null, $room->getSetting('locked'));

        // Test invalid option error
        $post = ['password' => 'eee', 'unknown' => 0];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/config", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame("Invalid room configuration option.", $json['errors']['unknown']);

        $room->refresh();
        $this->assertSame(null, $room->getSetting('password'));
    }

    /**
     * Test the webhook
     *
     * @group openvidu
     */
    public function testWebhook(): void
    {
        $this->assignMeetEntitlement('john@kolab.org');

        $john = $this->getTestUser('john@kolab.org');
        $room = Room::where('name', 'john')->first();

        // First, create the session
        $post = ['init' => 1];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $sessionId = $room->fresh()->session_id;

        // Test accepting a join request
        $room->requestSave('1234', ['nickname' => 'test']);

        $post = ['roomId' => $sessionId, 'requestId' => '1234', 'event' => 'joinRequestAccepted'];
        $response = $this->post("api/webhooks/meet", $post);
        $response->assertStatus(200);

        $request = $room->requestGet('1234');

        $this->assertSame(Room::REQUEST_ACCEPTED, $request['status']);

        // Test denying a join request
        $room->requestSave('1234', ['nickname' => 'test']);

        $post = ['roomId' => $sessionId, 'requestId' => '1234', 'event' => 'joinRequestDenied'];
        $response = $this->post("api/webhooks/meet", $post);
        $response->assertStatus(200);

        $request = $room->requestGet('1234');

        $this->assertSame(Room::REQUEST_DENIED, $request['status']);

        // Test closing the session
        $post = ['roomId' => $sessionId, 'event' => 'roomClosed'];
        $response = $this->post("api/webhooks/meet", $post);
        $response->assertStatus(200);

        $this->assertNull($room->fresh()->session_id);
    }
}
