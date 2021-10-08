<?php

namespace App\Console\Commands;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class UserForceDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:force-delete {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a user for realz';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'), true);

        if (!$user) {
            return 1;
        }

        if (!$user->trashed()) {
            $this->error('The user is not yet deleted');
            return 1;
        }

        DB::beginTransaction();
        $user->forceDelete();
        DB::commit();
    }
}