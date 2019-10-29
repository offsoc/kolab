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
    protected $signature = 'user:migrate
                                {src : Source account}
                                {dst : Destination account}';

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
        $src = new DataMigrator\Account($this->argument('src'));
        $dst = new DataMigrator\Account($this->argument('dst'));

        DataMigrator::migrate($src, $dst);
    }
}
