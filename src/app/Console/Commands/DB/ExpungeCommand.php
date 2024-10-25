<?php

namespace App\Console\Commands\DB;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpungeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:expunge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expunge old records from the database tables';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \App\Policy\Greylist\Connect::where('updated_at', '<', Carbon::now()->subMonthsWithoutOverflow(6))
            ->delete();

        \App\Policy\Greylist\Whitelist::where('updated_at', '<', Carbon::now()->subMonthsWithoutOverflow(6))
            ->delete();

        \App\Policy\RateLimit::where('updated_at', '<', Carbon::now()->subMonthsWithoutOverflow(6))
            ->delete();

        \App\SignupCode::where('created_at', '<', Carbon::now()->subMonthsWithoutOverflow(6))
            ->forceDelete();

        DB::table('failed_jobs')->where('failed_at', '<', Carbon::now()->subMonthsWithoutOverflow(6))
            ->delete();

        // TODO: What else? Should we force-delete deleted "dummy/spammer" accounts?
    }
}
