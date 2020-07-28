<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\V4\OpenViduController;
use App\OpenVidu\Room;
use Tests\TestCase;

class OpenViduTest extends TestCase
{
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

        // John has no room, but it will be auto-created
        $response = $this->actingAs($jack)->get("api/v4/openvidu/rooms");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('jack', $json['list'][0]['name']);
    }

    /**
     * Test joining the room
     *
     * @group openvidu
     */
    public function testJoin(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $room = Room::where('name', 'john')->first();
        $room->session_id = null;
        $room->save();

        // Unauth access not allowed (yet)
        $response = $this->get("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(401);

        // Non-existing room name
        $response = $this->actingAs($john)->get("api/v4/openvidu/rooms/non-existing");
        $response->assertStatus(404);

        // Non-owner, no session yet
        $response = $this->actingAs($jack)->get("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(423);

        // Room owner
        $response = $this->actingAs($john)->get("api/v4/openvidu/rooms/{$room->name}");
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
        $response = $this->actingAs($jack)->get("api/v4/openvidu/rooms/{$room->name}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('PUBLISHER', $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertTrue(!array_key_exists('shareToken', $json));

        $jack_token = $json['token'];

        // request with screenShare token
        $response = $this->actingAs($john)->get("api/v4/openvidu/rooms/{$room->name}?screenShare=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('PUBLISHER', $json['role']);
        $this->assertSame($session_id, $json['session']);
        $this->assertTrue(strpos($json['token'], 'wss://') === 0);
        $this->assertTrue($json['token'] != $john_token);
        $this->assertTrue(strpos($json['shareToken'], 'wss://') === 0);
        $this->assertTrue($json['shareToken'] != $john_token && $json['shareToken'] != $json['token']);
    }
}
