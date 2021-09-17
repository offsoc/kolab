<?php

namespace Tests;

use App\Meet\Room;

trait TestCaseMeetTrait
{
    /**
     * Assign 'meet' entitlement to a user.
     *
     * @param string|\App\User $user The user
     */
    protected function assignMeetEntitlement($user): void
    {
        if (is_string($user)) {
            $user = $this->getTestUser($user);
        }

        $user->assignSku(\App\Sku::where('title', 'meet')->first());
    }

    /**
     * Removes all 'meet' entitlements from the database
     */
    protected function clearMeetEntitlements(): void
    {
        $meet_sku = \App\Sku::where('title', 'meet')->first();
        \App\Entitlement::where('sku_id', $meet_sku->id)->delete();
    }

    /**
     * Reset a room after tests
     */
    public function resetTestRoom($room_name = 'john'): void
    {
        $this->clearMeetEntitlements();

        $room = Room::where('name', $room_name)->first();
        $room->setSettings(['password' => null, 'locked' => null, 'nomedia' => null]);

        if ($room->session_id) {
            $room->session_id = null;
            $room->save();
        }
    }

    /**
     * Prepare a room for testing
     */
    public function setupTestRoom($room_name = 'john'): void
    {
        $this->resetTestRoom($room_name);
        $this->assignMeetEntitlement('john@kolab.org');
    }
}
