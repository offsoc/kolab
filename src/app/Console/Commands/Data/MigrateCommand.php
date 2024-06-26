<?php

namespace App\Console\Commands\Data;

use App\DataMigrator;

use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:migrate
                                {src : Source account}
                                {dst : Destination account}
                                {--type= : Object type(s)}
                                {--force : Force existing queue removal}';
//                                {--export-only : Only export data}
//                                {--import-only : Only import previously exported data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate user data from an external service';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $src = new DataMigrator\Account($this->argument('src'));
        $dst = new DataMigrator\Account($this->argument('dst'));
        $options = [
            'type' => $this->option('type'),
            'force' => $this->option('force'),
            'stdout' => true,
        ];

        DataMigrator\Engine::migrate($src, $dst, $options);
    }
}
