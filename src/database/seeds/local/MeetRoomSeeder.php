<?php

namespace Database\Seeds\Local;

use App\Meet\Room;
use Illuminate\Database\Seeder;

class MeetRoomSeeder extends Seeder
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

        \App\Meet\Room::create(
            [
                'user_id' => $john->id,
                'name' => 'john'
            ]
        );

        \App\Meet\Room::create(
            [
                'user_id' => $jack->id,
                'name' => strtolower(\App\Utils::randStr(3, 3, '-'))
            ]
        );
    }
}
