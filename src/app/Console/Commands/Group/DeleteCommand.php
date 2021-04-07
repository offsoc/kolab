<?php

namespace App\Console\Commands\Group;

use App\Console\Command;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:delete {group}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete a group.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $input = $this->argument('group');
        $group = $this->getObject(\App\Group::class, $input, 'email');

        if (empty($group)) {
            $this->error("Group {$input} does not exist.");
            return 1;
        }

        $group->delete();
    }
}
