<?php

namespace App\Console\Commands\Policy\RateLimit;

use Illuminate\Console\Command;

class ExpungeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:ratelimit:expunge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expunge records from the policy ratelimit table';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \App\Policy\RateLimit::where('updated_at', '<', \Carbon\Carbon::now()->subMonthsWithoutOverflow(6))->delete();
    }
}
