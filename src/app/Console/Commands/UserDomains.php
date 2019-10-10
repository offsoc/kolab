<?php

namespace App\Console\Commands;

use App\Domain;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:domains {userid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        DB::enableQueryLog();

        $user = User::where('email', $this->argument('userid'))->first();

        $this->info("Found user: {$user->id}");

        foreach ($user->domains() as $domain) {
            $this->info("Domain: {$domain->namespace}");
        }

        dd(DB::getQueryLog());
    }
}
