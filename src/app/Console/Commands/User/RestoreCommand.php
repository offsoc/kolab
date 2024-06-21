<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreCommand extends Command
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
        $user = $this->getUser($this->argument('user'), true);

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        if (!$user->trashed()) {
            $this->error("The user is not deleted.");
            return 1;
        }

        DB::beginTransaction();
        $user->restore();
        DB::commit();
    }
}
