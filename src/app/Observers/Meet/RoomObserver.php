<?php

namespace App\Observers\Meet;

use App\Meet\Room;

class RoomObserver
{
    /**
     * Handle the room "created" event.
     *
     * @param \App\Meet\Room $room The room
     *
     * @return void
     */
    public function creating(Room $room): void
    {
        if (empty($room->name)) {
            // Generate a random and unique room name
            while (true) {
                $room->name = strtolower(\App\Utils::randStr(3, 3, '-'));
                if (!Room::where('name', $room->name)->exists()) {
                    break;
                }
            }
        }
    }
}
