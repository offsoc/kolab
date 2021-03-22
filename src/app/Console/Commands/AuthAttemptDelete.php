<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\AuthAttempt;

class AuthAttemptDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authattempt:delete {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete an AuthAttempt';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $authAttempt = AuthAttempt::findOrFail($this->argument('id'));

        if ($authAttempt == null) {
            return 1;
        }

        $authAttempt->delete();
    }
}
