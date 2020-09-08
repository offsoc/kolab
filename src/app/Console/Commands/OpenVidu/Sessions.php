<?php

namespace App\Console\Commands\OpenVidu;

use Illuminate\Console\Command;

class Sessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openvidu:sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List OpenVidu sessions';

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
        // curl -X GET -k -u OPENVIDUAPP:MY_SECRET https://localhost:4443/api/sessions, json

        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => \config('openvidu.api_url'),
                'verify' => \config('openvidu.api_verify_tls')
            ]
        );

        $response = $client->request(
            'GET',
            'sessions',
            ['auth' => [\config('openvidu.api_username'), \config('openvidu.api_password')]]
        );

        if ($response->getStatusCode() !== 200) {
            return 1;
        }

        $sessionResponse = json_decode($response->getBody(), true);

        foreach ($sessionResponse['content'] as $session) {
            $room = \App\OpenVidu\Room::where('session_id', $session['sessionId'])->first();
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
                    $session['sessionId'],
                    $roomName,
                    \Carbon\Carbon::parse((int)substr($session['createdAt'], 0, 10), 'UTC'),
                    $owner
                )
            );
        }
    }
}
