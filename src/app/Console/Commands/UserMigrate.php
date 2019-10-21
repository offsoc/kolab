<?php

namespace App\Console\Commands;

use App\DataMigrator;

use Illuminate\Console\Command;

class UserMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:migrate {user : E-mail address} {password : Password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate user data from an external service';

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
        $user = $this->argument('user');
        $pass = $this->argument('password');

        DataMigrator::migrate($user, $pass);
    }
}
