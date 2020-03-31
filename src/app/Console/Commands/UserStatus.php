<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class UserStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:status {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the status of a user';

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
        $user = User::where('email', $this->argument('user'))->first();

        if (!$user) {
            return 1;
        }

        $this->info("Found user: {$user->id}");

        $this->info($user->status);
    }
}
