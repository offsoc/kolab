<?php

namespace App\Console\Commands\Policy\Greylist;

use App\Policy\Greylist\Connect;
use App\Policy\Greylist\Whitelist;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpungeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:greylist:expunge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expunge old records from the policy greylist tables';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Connect::where('updated_at', '<', Carbon::now()->subMonthsWithoutOverflow(2))
            ->delete();

        Whitelist::where('updated_at', '<', Carbon::now()->subMonthsWithoutOverflow(2))
            ->delete();
    }
}
