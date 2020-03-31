<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UserVerify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:verify {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the state of a user account';

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

        $this->info("Found user: {$user->id}");

        $job = new \App\Jobs\UserVerify($user);
        $job->handle();
    }
}
