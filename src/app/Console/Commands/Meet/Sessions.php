<?php

namespace App\Console\Commands\Meet;

use App\Console\Command;

class Sessions extends Command
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
        $client = new \GuzzleHttp\Client([
                'http_errors' => false, // No exceptions from Guzzle
                'base_uri' => \config('meet.api_url'),
                'verify' => \config('meet.api_verify_tls'),
                'headers' => [
                    'X-Auth-Token' => \config('meet.api_token'),
                ],
                'connect_timeout' => 10,
                'timeout' => 10,
        ]);

        $response = $client->request('GET', 'sessions');

        if ($response->getStatusCode() !== 200) {
            return 1;
        }

        $sessions = json_decode($response->getBody(), true);

        foreach ($sessions as $session) {
            $room = \App\Meet\Room::where('session_id', $session['roomId'])->first();
            if ($room) {
                $owner = $room->owner->email;
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
                    \Carbon\Carbon::parse($session['createdAt'], 'UTC'),
                    $owner
                )
            );
        }
    }
}
