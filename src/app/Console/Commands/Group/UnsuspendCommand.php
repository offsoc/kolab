<?php

namespace App\Console\Commands\Group;

use App\Console\Command;

class UnsuspendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:unsuspend {group} {--comment=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a group suspension';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $group = $this->getGroup($this->argument('group'));

        if (!$group) {
            $this->error("Group not found.");
            return 1;
        }

        $group->unsuspend();

        \App\EventLog::createFor($group, \App\EventLog::TYPE_UNSUSPENDED, $this->option('comment'));
    }
}
