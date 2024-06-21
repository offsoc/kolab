<?php

namespace Tests\Feature;

use App\Meet\Room;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MeetRoomTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('room-test@' . \config('app.domain'));
        Room::withTrashed()->whereNotIn('name', ['shared', 'john'])->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('room-test@' . \config('app.domain'));
        Room::withTrashed()->whereNotIn('name', ['shared', 'john'])->forceDelete();

        parent::tearDown();
    }

    /**
     * Test room/user creation
     */
    public function testCreate(): void
    {
        Queue::fake();

        // Test default room name generation
        $room = new Room();
        $room->save();

        $this->assertMatchesRegularExpression('/^[0-9a-z]{3}-[0-9a-z]{3}-[0-9a-z]{3}$/', $room->name);

        // Test keeping the specified room name
        $room = new Room();
        $room->name = 'test';
        $room->save();

        $this->assertSame('test', $room->name);
    }

    /**
     * Test room/user deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('room-test@' . \config('app.domain'));
        $wallet = $user->wallets()->first();
        $room = $this->getTestRoom('test', $wallet, [], [
                'password' => 'test',
                'acl' => ['john@kolab.org, full'],
            ], 'group-room');

        $this->assertCount(1, $room->entitlements()->get());
        $this->assertCount(1, $room->permissions()->get());
        $this->assertCount(1, $room->settings()->get());

        // First delete the room, see if it deletes the room permissions, entitlements and settings
        $room->delete();

        $this->assertTrue($room->fresh()->trashed());
        $this->assertCount(0, $room->entitlements()->get());
        $this->assertCount(0, $room->permissions()->get());
        $this->assertCount(1, $room->settings()->get());

        $room->forceDelete();

        $this->assertCount(0, Room::where('name', 'test')->get());
        $this->assertCount(0, $room->settings()->get());

        // Now test if deleting a user deletes the room
        $room = $this->getTestRoom('test', $wallet, [], [
                'password' => 'test',
                'acl' => ['john@kolab.org, full'],
            ], 'group-room');

        $user->delete();

        $this->assertTrue($room->fresh()->trashed());
        $this->assertCount(0, $room->entitlements()->get());
        $this->assertCount(0, $room->permissions()->get());
        $this->assertCount(1, $room->settings()->get());
    }

    /**
     * Test getConfig()/setConfig() (\App\Meet\RoomConfigTrait)
     */
    public function testConfig(): void
    {
        $room = $this->getTestRoom('test');
        $user = $this->getTestUser('room-test@' . \config('app.domain'));
        $wallet = $user->wallets()->first();

        // Test input validation (acl can be set on a group room only
        $result = $room->setConfig($input = [
            'acl' => ['jack@kolab.org, full'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame("The requested configuration parameter is not supported.", $result['acl']);

        $room->entitlements()->delete();
        $room->assignToWallet($wallet, 'group-room');

        // Test input validation
        $result = $room->setConfig($input = [
            'acl' => ['jack@kolab.org, read-only', 'test@unknown.org, full'],
        ]);

        $this->assertCount(2, $result['acl']);
        $this->assertSame("The specified permission is invalid.", $result['acl'][0]);
        $this->assertSame("The specified email address does not exist.", $result['acl'][1]);

        $room->setConfig($input = [
            'password' => 'test-pass',
            'nomedia' => true,
            'locked' => true,
            'acl' => ['john@kolab.org, full']
        ]);

        $config = $room->getConfig();

        $this->assertCount(4, $config);
        $this->assertSame($input['password'], $config['password']);
        $this->assertSame($input['nomedia'], $config['nomedia']);
        $this->assertSame($input['locked'], $config['locked']);
        $this->assertSame($input['acl'], $config['acl']);
    }
}
