<?php

namespace App\Console\Commands\OpenVidu;

use Illuminate\Console\Command;

class Rooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openvidu:rooms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List OpenVidu rooms';

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
        $rooms = \App\OpenVidu\Room::all();

        foreach ($rooms as $room) {
            $this->info("{$room->name}");
        }
    }
}
