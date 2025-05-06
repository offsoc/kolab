<?php

namespace App\Console\Commands\Meet;

use App\Console\Command;
use App\Meet\Room;

class RoomsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meet:rooms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Meet rooms';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $rooms = Room::all();

        foreach ($rooms as $room) {
            $this->info("{$room->name}");
        }
    }
}
