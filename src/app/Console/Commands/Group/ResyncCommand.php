<?php

namespace App\Console\Commands\Group;

use App\Console\Command;
use App\Group;

class ResyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:resync {group?} {--deleted-only} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Re-Synchronize groups with the imap/ldap backend(s)";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $group = $this->argument('group');
        $deleted_only = $this->option('deleted-only');
        $dry_run = $this->option('dry-run');
        $with_ldap = \config('app.with_ldap');

        if (!empty($group)) {
            if ($req_group = $this->getGroup($group, true)) {
                $groups = [$req_group];
            } else {
                $this->error("Group not found.");
                return 1;
            }
        } else {
            $groups = Group::withTrashed();

            if ($deleted_only) {
                $groups->whereNotNull('deleted_at')
                    ->where(function ($query) {
                        $query->where('status', '&', Group::STATUS_LDAP_READY);
                    });
            }

            $groups = $groups->orderBy('id')->cursor();
        }

        // TODO: Maybe we should also have account:resync, domain:resync, resource:resync and so on.

        foreach ($groups as $group) {
            if ($group->trashed()) {
                if ($with_ldap && $group->isLdapReady()) {
                    if ($dry_run) {
                        $this->info("{$group->email}: will be pushed");
                        continue;
                    }

                    if ($group->isDeleted()) {
                        // Remove the DELETED flag so the DeleteJob can do the work
                        $group->timestamps = false;
                        $group->update(['status' => $group->status ^ Group::STATUS_DELETED]);
                    }

                    // TODO: Do this not asyncronously as an option or when a signle group is requested?
                    \App\Jobs\Group\DeleteJob::dispatch($group->id);

                    $this->info("{$group->email}: pushed");
                } else {
                    // Group properly deleted, no need to push.
                    // Here potentially we could connect to ldap/imap backend and check to be sure
                    // that the group is really deleted no matter what status it has in the database.

                    $this->info("{$group->email}: in-sync");
                }
            } else {
                if (!$group->isActive() || ($with_ldap && !$group->isLdapReady())) {
                    if ($dry_run) {
                        $this->info("{$group->email}: will be pushed");
                        continue;
                    }

                    \App\Jobs\Group\CreateJob::dispatch($group->id);

                    $this->info("{$group->email}: pushed");
                } elseif (!empty($req_group)) {
                    if ($dry_run) {
                        $this->info("{$group->email}: will be pushed");
                        continue;
                    }

                    // We push the update only if a specific group is requested,
                    // We don't want to flood the database/backend with an update of all groups
                    \App\Jobs\Group\UpdateJob::dispatch($group->id);

                    $this->info("{$group->email}: pushed");
                } else {
                    $this->info("{$group->email}: in-sync");
                }
            }
        }
    }
}
