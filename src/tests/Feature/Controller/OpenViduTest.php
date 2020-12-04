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
        $response->assertStatus(423);

        // Non-existing room name
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/non-existing");
        $response->assertStatus(404);

        // Non-owner, no session yet
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(423);

        // Room owner, no session yet
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(424);

        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}", ['init' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $session_id = $room->fresh()->session_id;

        $this->assertSame('PUBLISHER', $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue(is_string($session_id) && !empty($session_id));
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue(!array_key_exists('shareToken', $json));

        $john_token = $json['token'];

        // Non-owner, now the session exists
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('PUBLISHER', $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertTrue(!array_key_exists('shareToken', $json));
        $this->assertEmpty($json['config']['password']);
        $this->assertEmpty($json['config']['requires_password']);

        // Non-owner, password protected room, password not provided
        $room->setSettings(['password' => 'pass']);
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(425);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame('Failed to join the session. Invalid password.', $json['message']);
        $this->assertEmpty($json['config']['password']);
        $this->assertTrue($json['config']['requires_password']);

        // Non-owner, password protected room, invalid provided
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['password' => 'aa']);
        $response->assertStatus(425);

        // Non-owner, password protected room, valid password provided
        $response = $this->actingAs($jack)->post("api/v4/openvidu/rooms/{$room->name}", ['password' => 'pass']);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($session_id, $json['session']);

        // Make sure the room owner can access the password protected room w/o password
        $response = $this->actingAs($john)->post("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(200);

        // TODO: Test accessing an existing room of deleted owner
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
        $response = $this->post("api/v4/openvidu/rooms/{$room->name}", ['screenShare' => 1]);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('PUBLISHER', $json['role']);
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
