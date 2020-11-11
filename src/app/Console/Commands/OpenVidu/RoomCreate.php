<?php

namespace App\Console\Commands\OpenVidu;

use Illuminate\Console\Command;

class RoomCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openvidu:room-create {user} {room}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a room for a user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = \App\User::where('email', $this->argument('user'))->first();

        if (!$user) {
            return 1;
        }

        $roomName = $this->argument('room');

        if (!preg_match('/^[a-zA-Z0-9_-]{1,16}$/', $roomName)) {
            $this->error("Invalid room name. Should be up to 16 characters ([a-zA-Z0-9_-]).");
            return 1;
        }

        $room = \App\OpenVidu\Room::where('name', $roomName)->first();

        if ($room) {
            $this->error("Room already exists.");
            return 1;
        }

        \App\OpenVidu\Room::create(
            [
                'name' => $roomName,
                'user_id' => $user->id
            ]
        );
    }
}
