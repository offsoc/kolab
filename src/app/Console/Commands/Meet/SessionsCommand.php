<?php

namespace App\Console\Commands\Meet;

use App\Console\Command;
use App\Meet\Room;
use App\Meet\Service;
use Carbon\Carbon;

class SessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meet:sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Meet sessions';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $response = Service::client()->get('sessions')->throwUnlessStatus(200);

        foreach ($response->json() as $session) {
            $room = Room::where('session_id', $session['roomId'])->first();
            if ($room) {
                $owner = $room->walletOwner()->email;
                $roomName = $room->name;
            } else {
                $owner = '(none)';
                $roomName = '(none)';
            }

            $this->info(
                sprintf(
                    "Session: %s for %s since %s (by %s)",
                    $session['roomId'],
                    $roomName,
                    Carbon::parse($session['createdAt'], 'UTC'),
                    $owner
                )
            );
        }
    }
}
