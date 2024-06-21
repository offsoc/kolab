<?php

namespace Database\Seeds;

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
        $wallet = $john->wallets()->first();

        $rooms = [
            [
                'name' => 'john',
                'description' => "Standard room"
            ],
            [
                'name' => 'shared',
                'description' => "Shared room"
            ]
        ];

        foreach ($rooms as $idx => $room) {
            $room = \App\Meet\Room::create($room);
            $rooms[$idx] = $room;
        }

        $rooms[0]->assignToWallet($wallet, 'room');
        $rooms[1]->assignToWallet($wallet, 'group-room');
        $rooms[1]->setConfig(['acl' => 'jack@kolab.org, full']);
    }
}
