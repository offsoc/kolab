<?php

namespace App\Console\Commands\DB;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyTimezoneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:verify-timezone';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Verify the application's timezone compared to the DB timezone";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $result = \Illuminate\Support\Facades\DB::select("SHOW VARIABLES WHERE Variable_name = 'time_zone'");

        $appTimezone = \config('app.timezone');

        if ($appTimezone != "UTC") {
            $this->error("The application timezone is not configured to be UTC");
            return 1;
        }

        if ($result[0]->{'Value'} != '+00:00' && $result[0]->{'Value'} != 'UTC') {
            $this->error("The database timezone is neither configured as '+00:00' nor 'UTC'");
            return 1;
        }

        return 0;
    }
}
