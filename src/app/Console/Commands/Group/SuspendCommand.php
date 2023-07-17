<?php

namespace App\Console\Commands\Group;

use App\Console\Command;

class SuspendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:suspend {group} {--comment=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend a distribution list (group)';

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

        $group->suspend();

        \App\EventLog::createFor($group, \App\EventLog::TYPE_SUSPENDED, $this->option('comment'));
    }
}
