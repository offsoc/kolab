<?php

namespace Database\Seeds\Local;

use App\OpenVidu\Room;
use Illuminate\Database\Seeder;

// phpcs:ignore
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
                'name' => 'john'
            ]
        );
    }
}
