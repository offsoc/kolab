<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DBPing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:ping {--wait}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ping the database [and wait for it to respond]';

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
        if ($this->option('wait')) {
            while (true) {
                try {
                    $result = DB::select("SELECT 1");

                    if (sizeof($result) > 0) {
                        break;
                    }
                } catch (\Exception $exception) {
                    sleep(1);
                }
            }
        } else {
            try {
                $result = DB::select("SELECT 1");
                return 0;
            } catch (\Exception $exception) {
                return 1;
            }
        }
    }
}
