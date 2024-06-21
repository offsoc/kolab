<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\Command;

class AliasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharedfolder:aliases {folder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List shared folder's aliases";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $folder = $this->getSharedFolder($this->argument('folder'));

        if (!$folder) {
            $this->error("Folder not found.");
            return 1;
        }

        foreach ($folder->aliases()->pluck('alias')->all() as $alias) {
            $this->info($alias);
        }
    }
}
