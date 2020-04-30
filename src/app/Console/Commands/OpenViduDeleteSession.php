<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OpenViduDeleteSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openvidu:delete-session {session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a session';

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
        // curl -X DELETE -k -u OPENVIDUAPP:MY_SECRET https://localhost:4443/api/sessions/id, json
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => \config('openvidu.api_url'),
                'verify' => \config('openvidu.api_verify_tls')
            ]
        );

        $room = \App\OpenVidu\Room::where('session_id', $this->argument('session'))->first();

        if ($room) {
            $room->delete();
        }
        // TODO: Tolerant

        // TODO: observer dispatch?
        $response = $client->request(
            'DELETE',
            "sessions/{$this->argument('session')}",
            [
                'auth' => [\config('openvidu.api_username'), \config('openvidu.api_password')]
            ]
        );

        if ($response->getStatusCode() !== 204) {
            return 1;
        }
    }
}
