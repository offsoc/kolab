<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\Command;
use App\Http\Controllers\API\V4\SharedFoldersController;

class AddAliasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharedfolder:add-alias {--force} {folder} {alias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Add an email alias to a shared folder (forcefully)";

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

        $alias = \strtolower($this->argument('alias'));

        // Check if the alias already exists
        if ($folder->aliases()->where('alias', $alias)->first()) {
            $this->error("Address is already assigned to the folder.");
            return 1;
        }

        // Validate the alias
        $domain = explode('@', $folder->email, 2)[1];

        $error = SharedFoldersController::validateAlias($alias, $folder->walletOwner(), $folder->name, $domain);

        if ($error) {
            if (!$this->option('force')) {
                $this->error($error);
                return 1;
            }
        }

        $folder->aliases()->create(['alias' => $alias]);
    }
}
