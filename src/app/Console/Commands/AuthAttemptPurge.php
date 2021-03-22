<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\AuthAttempt;
use Carbon\Carbon;

class AuthAttemptPurge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authattempt:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old AuthAttempts from the database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subDays(30);
        \App\AuthAttempt::where('updated_at', '<', $cutoff)
            ->delete();
    }
}
