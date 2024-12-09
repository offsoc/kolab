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
    protected $signature = 'user:resync {user?} {--deleted-only} {--created-only} {--dry-run} {--min-age=} {--limit=}';

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
        $created_only = $this->option('created-only');
        $dry_run = $this->option('dry-run');
        $min_age = $this->option('min-age');
        $limit = $this->option('limit');
        $with_ldap = \config('app.with_ldap');
        $req_user = null;
        $createdUsers = null;
        $deletedUsers = null;

        if (!empty($user)) {
            if ($req_user = $this->getUser($user, true)) {
                // $this->error("req user {$req_user}.");
                $deletedUsers = User::onlyTrashed()->where('id', $req_user->id);
                $createdUsers = User::where('id', $req_user->id);
            } else {
                $this->error("User not found.");
                return 1;
            }
        } else {
            if (!$created_only) {
                $deletedUsers = User::onlyTrashed();
            }
            if (!$deleted_only) {
                $createdUsers = User::withoutTrashed();
            }
        }

        if ($deletedUsers) {
            $deletedUsers = $deletedUsers->where(function ($query) use ($with_ldap) {
                $query = $query->where('role', '!=', User::ROLE_SERVICE)
                    ->where('status', '&', User::STATUS_IMAP_READY);
                if ($with_ldap) {
                    $query->orWhere('status', '&', User::STATUS_LDAP_READY);
                }
            });
        }

        if ($createdUsers) {
            $createdUsers = $createdUsers->where(function ($query) use ($with_ldap) {
                $query = $query->where('role', '!=', User::ROLE_SERVICE)
                    ->whereNot('status', '&', User::STATUS_IMAP_READY)
                    ->orWhereNot('status', '&', User::STATUS_ACTIVE);
                if ($with_ldap) {
                    $query->orWhereNot('status', '&', User::STATUS_LDAP_READY);
                }
            });
        }

        if ($min_age) {
            if (preg_match('/^([0-9]+)([mdy])$/i', $min_age, $matches)) {
                $count = (int) $matches[1];
                $period = strtolower($matches[2]);
                $date = \Carbon\Carbon::now();

                if ($period == 'y') {
                    $date->subYearsWithoutOverflow($count);
                } elseif ($period == 'm') {
                    $date->subMonthsWithoutOverflow($count);
                } else {
                    $date->subDays($count);
                }
                if ($createdUsers) {
                    $createdUsers = $createdUsers->where('created_at', '<=', $date);
                }
                if ($deletedUsers) {
                    $deletedUsers = $deletedUsers->where('deleted_at', '<=', $date);
                }
            } else {
                $this->error("Invalid --min-age.");
                return 1;
            }
        }

        // TODO: Maybe we should also have account:resync, domain:resync, resource:resync and so on.

        $count = 0;

        // Push create jobs for users that should be created, but aren't fully
        if ($createdUsers) {
            $createdUsers = $createdUsers->orderBy('id')->cursor();
            foreach ($createdUsers as $user) {
                if ($limit > 0 && $count > $limit) {
                    $this->info("Reached limit of $limit");
                    break;
                }

                $count++;
                if ($dry_run) {
                    $this->info("{$user->email}: will be pushed");
                    continue;
                }

                \App\Jobs\User\CreateJob::dispatch($user->id);
                $this->info("{$user->email}: pushed (create)");
            }
        }

        // Push delete jobs for users that should be deleted, but aren't fully
        if ($deletedUsers) {
            $deletedUsers = $deletedUsers->orderBy('id')->cursor();
            foreach ($deletedUsers as $user) {
                if ($limit > 0 && $count > $limit) {
                    $this->info("Reached limit of $limit");
                    break;
                }

                $count++;
                if ($dry_run) {
                    $this->info("{$user->email}: will be pushed");
                    continue;
                }

                if ($user->isDeleted()) {
                    // Remove the DELETED flag so the DeleteJob can do the work
                    $user->timestamps = false;
                    $user->update(['status' => $user->status ^ User::STATUS_DELETED]);
                }

                \App\Jobs\User\DeleteJob::dispatch($user->id);
                $this->info("{$user->email}: pushed (delete)");
            }
        }

        // Push a resync job if none of the above matched and we requested a specific user
        if ($req_user && $count == 0) {
            if ($dry_run) {
                $this->info("{$req_user->email}: will be pushed");
            } else {
                if ($req_user->trashed()) {
                    $this->info("{$req_user->email}: in-sync");
                } else {
                    // We push the update only if a specific user is requested,
                    // We don't want to flood the database/backend with an update of all users
                    \App\Jobs\User\ResyncJob::dispatch($req_user->id);
                    $this->info("{$req_user->email}: pushed (resync)");
                }
            }
        }
    }
}
