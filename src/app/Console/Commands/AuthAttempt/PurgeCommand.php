<?php

namespace App\Console\Commands\AuthAttempt;

use App\AuthAttempt;
use App\Console\Command;
use Carbon\Carbon;

class PurgeCommand extends Command
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
        AuthAttempt::where('updated_at', '<', $cutoff)
            ->delete();
    }
}
