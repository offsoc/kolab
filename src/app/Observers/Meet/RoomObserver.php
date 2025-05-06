<?php

namespace App\Observers\Meet;

use App\Meet\Room;
use App\Utils;

class RoomObserver
{
    /**
     * Handle the room "created" event.
     *
     * @param Room $room The room
     */
    public function creating(Room $room): void
    {
        if (empty($room->name)) {
            // Generate a random and unique room name
            while (true) {
                $room->name = strtolower(Utils::randStr(3, 3, '-'));
                if (!Room::where('name', $room->name)->exists()) {
                    break;
                }
            }
        }
    }
}
