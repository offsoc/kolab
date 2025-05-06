<?php

namespace App\Console\Commands\AuthAttempt;

use App\AuthAttempt;
use App\Console\Command;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authattempts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List auth attempts';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $authAttempts = AuthAttempt::orderBy('last_seen');

        $authAttempts->each(
            function ($authAttempt) {
                $this->info($authAttempt->toJson(\JSON_PRETTY_PRINT));
            }
        );
    }
}
