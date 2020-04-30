<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OpenViduCreateSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openvidu:create-session {user} {--session-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a session for a user [with session ID]';

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
        $user = \App\User::where('email', $this->argument('user'))->first();

        if (!$user) {
            return 1;
        }

        // curl -X POST -k -u OPENVIDUAPP:MY_SECRET https://localhost:4443/api/sessions, json
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => \config('openvidu.api_url'),
                'verify' => \config('openvidu.api_verify_tls')
            ]
        );

        // https://docs.openvidu.io/en/2.13.0/reference-docs/REST-API/#post-apisessions
        $json = [
            'mediaMode' => 'ROUTED',
            'recordingMode' => 'MANUAL',
        ];

        if ($this->option('session-id')) {
            $room = \App\OpenVidu\Room::where('session_id', $this->option('session-id'))->first();
            if ($room) {
                $this->error("Room already exists.");
                return 1;
            }

            // TODO: [0-9a-zA-Z-_]{16}
            $json['customSessionId'] = $this->option('session-id');
        }

        // TODO: observer dispatch?
        $response = $client->request(
            'POST',
            'sessions',
            [
                'auth' => [\config('openvidu.api_username'), \config('openvidu.api_password')],
                'json' => $json
            ]
        );

        if ($response->getStatusCode() !== 200) {
            return 1;
        }

        $responseJson = json_decode($response->getBody(), true);

        $room = \App\OpenVidu\Room::create(
            [
                'session_id' => $responseJson['id'],
                'user_id' => $user->id
            ]
        );
    }
}
