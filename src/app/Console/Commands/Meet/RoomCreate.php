<?php

namespace App\Console\Commands\Meet;

use App\Console\Command;
use App\Meet\Room;

class RoomCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meet:room-create {user} {room}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a room for a user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            return 1;
        }

        $roomName = $this->argument('room');

        if (!preg_match('/^[a-zA-Z0-9_-]{1,16}$/', $roomName)) {
            $this->error("Invalid room name. Should be up to 16 characters ([a-zA-Z0-9_-]).");
            return 1;
        }

        $room = Room::where('name', $roomName)->first();

        if ($room) {
            $this->error("Room already exists.");
            return 1;
        }

        $room = new Room();
        $room->name = $roomName;
        $room->tenant_id = $user->tenant_id;
        $room->save();

        $room->assignToWallet($user->wallets()->first());
    }
}
