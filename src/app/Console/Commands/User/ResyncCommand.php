<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\User;

class ResyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:resync {user?} {--deleted-only} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Re-Synchronize users with the imap/ldap backend(s)";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->argument('user');
        $deleted_only = $this->option('deleted-only');
        $dry_run = $this->option('dry-run');
        $with_ldap = \config('app.with_ldap');

        if (!empty($user)) {
            if ($req_user = $this->getUser($user, true)) {
                $users = [$req_user];
            } else {
                $this->error("User not found.");
                return 1;
            }
        } else {
            $users = User::withTrashed();

            if ($deleted_only) {
                $users->whereNotNull('deleted_at')
                    ->where(function ($query) {
                        $query->where('status', '&', User::STATUS_IMAP_READY)
                            ->orWhere('status', '&', User::STATUS_LDAP_READY);
                    });
            }

            $users = $users->orderBy('id')->cursor();
        }

        // TODO: Maybe we should also have account:resync, domain:resync, resource:resync and so on.

        foreach ($users as $user) {
            if ($user->trashed()) {
                if (($with_ldap && $user->isLdapReady()) || $user->isImapReady()) {
                    if ($dry_run) {
                        $this->info("{$user->email}: will be pushed");
                        continue;
                    }

                    if ($user->isDeleted()) {
                        // Remove the DELETED flag so the DeleteJob can do the work
                        $user->timestamps = false;
                        $user->update(['status' => $user->status ^ User::STATUS_DELETED]);
                    }

                    // TODO: Do this not asyncronously as an option or when a signle user is requested?
                    \App\Jobs\User\DeleteJob::dispatch($user->id);

                    $this->info("{$user->email}: pushed");
                } else {
                    // User properly deleted, no need to push.
                    // Here potentially we could connect to ldap/imap backend and check to be sure
                    // that the user is really deleted no matter what status it has in the database.

                    $this->info("{$user->email}: in-sync");
                }
            } else {
                if (!$user->isActive() || ($with_ldap && !$user->isLdapReady()) || !$user->isImapReady()) {
                    if ($dry_run) {
                        $this->info("{$user->email}: will be pushed");
                        continue;
                    }

                    \App\Jobs\User\CreateJob::dispatch($user->id);
                } elseif (!empty($req_user)) {
                    if ($dry_run) {
                        $this->info("{$user->email}: will be pushed");
                        continue;
                    }

                    // We push the update only if a specific user is requested,
                    // We don't want to flood the database/backend with an update of all users
                    \App\Jobs\User\UpdateJob::dispatch($user->id);

                    $this->info("{$user->email}: pushed");
                } else {
                    $this->info("{$user->email}: in-sync");
                }
            }
        }
    }
}
