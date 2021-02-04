<?php

namespace App\Console\Commands\Group;

use App\Console\Command;

class AddMemberCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:add-member {group} {member}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Add a member to a group.";

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

        if (in_array($member, $group->members)) {
            $this->error("{$member}: Already exists in the group.");
            return 1;
        }

        if ($error = CreateCommand::validateMemberEmail($member)) {
            $this->error("{$member}: $error");
            return 1;
        }

        // We can't modify the property indirectly, therefor array_merge()
        $group->members = array_merge($group->members, [$member]);
        $group->save();
    }
}