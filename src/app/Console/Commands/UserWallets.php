<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UserWallets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:wallets {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List wallets for a user';

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

        foreach ($user->wallets as $wallet) {
            $this->info("{$wallet->id} {$wallet->description}");
        }
    }
}
