<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharedfolder:force-delete {folder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a shared folder for realz';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $folder = $this->getSharedFolder($this->argument('folder'), true);

        if (!$folder) {
            $this->error("Shared folder not found.");
            return 1;
        }

        if (!$folder->trashed()) {
            $this->error("The shared folder is not yet deleted.");
            return 1;
        }

        DB::beginTransaction();
        $folder->forceDelete();
        DB::commit();
    }
}
