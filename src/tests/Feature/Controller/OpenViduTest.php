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

        $this->clearBetaEntitlements();
        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null]);
    }

    public function tearDown(): void
    {
        $this->clearBetaEntitlements();
        $room = Room::where('name', 'john')->first();
        $room->setSettings(['password' => null, 'locked' => null]);

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
        $this->assertRegExp('/^[0-9a-z-]{11}$/', $json['list'][0]['name']);
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

        $this->assignBetaEntitlement($john, 'meet');

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

        $this->assertSame(Room::ROLE_MODERATOR, $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertFalse($json['canPublish']);
        $this->assertTrue(is_string($session_id) && !empty($session_id));
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue(!array_key_exists('shareToken', $json));

        $john_token = $json['token'];

        // Non-owner, now the session exists, no 'init' argument
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame(322, $json['code']);
        $this->assertTrue(empty($json['token']));
        $this->assertTrue(empty($json['shareToken']));

        // Non-owner, now the session exists, with 'init', but no 'canPublish' argument
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_SUBSCRIBER, $json['role']);
        $this->assertFalse($json['canPublish']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertTrue(empty($json['shareToken']));

        // Non-owner, now the session exists, with 'init', and with 'role=PUBLISHER'
        $post = ['canPublish' => true, 'init' => 1];
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue($json['canPublish']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertTrue(!array_key_exists('shareToken', $json));
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

        $json = $response->json();

        $this->assertSame($session_id, $json['session']);

        // Make sure the room owner can access the password protected room w/o password
        // TODO: Test without init=1
        $post = ['init' => 'init'];
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);
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

        $this->assignBetaEntitlement($john, 'meet');

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

        // TODO: How do we assert that a signal has been sent to the owner?

        // Test denying a request

        // Unknown room
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/unknown/request/unknown/deny");
        $response->assertStatus(404);

        // Unknown request Id
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/request/unknown/deny");
        $response->assertStatus(500);
        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to deny the join request.', $json['message']);

        // Non-owner access forbidden
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}/request/{$reqId}/deny");
        $response->assertStatus(403);

        // Valid request
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/request/{$reqId}/deny");
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame('success', $json['status']);

        // Non-owner, locked room, join request denied
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(422);

        $json = $response->json();
        $this->assertSame(327, $json['code']);

        // Test accepting a request

        // Unknown room
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/unknown/request/unknown/accept");
        $response->assertStatus(404);

        // Unknown request Id
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/request/unknown/accept");
        $response->assertStatus(500);
        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to accept the join request.', $json['message']);

        // Non-owner access forbidden
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}/request/{$reqId}/accept");
        $response->assertStatus(403);

        // Valid request
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/request/{$reqId}/accept");
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame('success', $json['status']);

        // Non-owner, locked room, join request accepted
        $post['init'] = 1;
        $post['canPublish'] = true;
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertTrue($json['canPublish']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);

        // TODO: Test a scenario where both password and lock are enabled
    }

    /**
     * Test joining the room
     *
     * @group openvidu
     * @depends testJoinRoom
     */
    public function testJoinRoomGuest(): void
    {
        $this->assignBetaEntitlement('john@kolab.org', 'meet');

        // There's no asy way to logout the user in the same test after
        // using actingAs(). That's why this is moved to a separate test
        $room = Room::where('name', 'john')->first();

        // Guest, request with screenShare token
        $post = ['canPublish' => true, 'screenShare' => 1, 'init' => 1];
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(Room::ROLE_PUBLISHER, $json['role']);
        $this->assertTrue($json['canPublish']);
        $this->assertSame($room->session_id, $json['session']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue(strpos($json['shareToken'], 'wss://') === 0);
        $this->assertTrue($json['shareToken'] != $json['token']);
    }

    /**
     * Test closing the room (session)
     *
     * @group openvidu
     * @depends testJoinRoom
     */
    public function testCloseRoom(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();

        // Unauth access not allowed
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}/close", []);
        $response->assertStatus(401);

        // Non-existing room name
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/non-existing/close", []);
        $response->assertStatus(404);

        // Non-owner
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}/close", []);
        $response->assertStatus(403);

        // Room owner
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/close", []);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertNull($room->fresh()->session_id);
        $this->assertSame('success', $json['status']);
        $this->assertSame("The session has been closed successfully.", $json['message']);
        $this->assertCount(2, $json);

        // TODO: Test if the session is removed from the OpenVidu server too

        // Test error handling when it's not possible to delete the session on
        // the OpenVidu server (use fake session_id)
        $room->session_id = 'aaa';
        $room->save();

        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/close", []);
        $response->assertStatus(500);

        $json = $response->json();

        $this->assertSame('aaa', $room->fresh()->session_id);
        $this->assertSame('error', $json['status']);
        $this->assertSame("Failed to close the session.", $json['message']);
        $this->assertCount(2, $json);
    }

    /**
     * Test dismissing a participant (closing a connection)
     *
     * @group openvidu
     */
    public function testDismissConnection(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();
        $room->session_id = null;
        $room->save();

        $this->assignBetaEntitlement($john, 'meet');

        // First we create the session
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        // And the other user connection
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $conn_id = $json['connectionId'];
        $room->refresh();
        $conn_data = $room->getOVConnection($conn_id);

        $this->assertSame($conn_id, $conn_data['connectionId']);

        // Non-existing room name
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/non-existing/connections/{$conn_id}/dismiss");
        $response->assertStatus(404);

        // TODO: Test accessing an existing room of deleted owner

        // Non-existing connection
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/connections/123/dismiss");
        $response->assertStatus(500);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to dismiss the connection.', $json['message']);

        // Non-owner access
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}/connections/{$conn_id}/dismiss");
        $response->assertStatus(403);

        // Expected success
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}/connections/{$conn_id}/dismiss");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertNull($room->getOVConnection($conn_id));
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
}
