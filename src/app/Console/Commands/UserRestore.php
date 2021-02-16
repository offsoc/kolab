<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:restore {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore (undelete) a user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = \App\User::withTrashed()->where('email', $this->argument('user'))->first();

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        if (!$user->trashed()) {
            $this->error('The user is not yet deleted.');
            return 1;
        }

        DB::beginTransaction();
        $user->restore();
        DB::commit();
    }
}
