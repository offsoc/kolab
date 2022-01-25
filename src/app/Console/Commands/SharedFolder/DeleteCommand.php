<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\Command;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharedfolder:delete {folder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete a shared folder.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $input = $this->argument('folder');
        $folder = $this->getSharedFolder($input);

        if (empty($folder)) {
            $this->error("Shared folder {$input} does not exist.");
            return 1;
        }

        $folder->delete();
    }
}
