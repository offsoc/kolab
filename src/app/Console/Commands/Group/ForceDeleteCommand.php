<?php

namespace App\Console\Commands\Group;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:force-delete {group}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a group for realz';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $group = $this->getGroup($this->argument('group'), true);

        if (!$group) {
            $this->error("Group not found.");
            return 1;
        }

        if (!$group->trashed()) {
            $this->error("The group is not yet deleted.");
            return 1;
        }

        DB::beginTransaction();
        $group->forceDelete();
        DB::commit();
    }
}
