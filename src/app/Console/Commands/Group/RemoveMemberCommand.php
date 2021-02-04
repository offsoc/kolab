<?php

namespace App\Console\Commands\Group;

use App\Console\Command;

class RemoveMemberCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:remove-member {group} {member}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remove a member from a group.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $input = $this->argument('group');
        $member = \strtolower($this->argument('member'));

        $group = $this->getObject(\App\Group::class, $input, 'email');

        if (empty($group)) {
            $this->error("Group {$input} does not exist.");
            return 1;
        }

        $members = [];

        foreach ($group->members as $m) {
            if ($m !== $member) {
                $members[] = $m;
            }
        }

        if (count($members) == count($group->members)) {
            $this->error("Member {$member} not found in the group.");
            return 1;
        }

        $group->members = $members;
        $group->save();
    }
}