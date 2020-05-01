<?php

use App\OpenVidu\Room;
use Illuminate\Database\Seeder;

class OpenViduRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = \App\User::where('email', 'john@kolab.org')->first();
        $room = \App\OpenVidu\Room::create(
            [
                'user_id' => $user->id,
                'session_id' => 'john'
            ]
        );
    }
}
