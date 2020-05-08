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
        $john = \App\User::where('email', 'john@kolab.org')->first();
        $jack = \App\User::where('email', 'jack@kolab.org')->first();

        \App\OpenVidu\Room::create(
            [
                'user_id' => $john->id,
                'name' => 'john'
            ]
        );

        \App\OpenVidu\Room::create(
            [
                'user_id' => $jack->id,
                'name' => strtolower(\App\Utils::randStr(3, 3, '-'))
            ]
        );
    }
}
