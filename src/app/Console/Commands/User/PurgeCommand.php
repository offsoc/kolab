<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\User;
use Illuminate\Database\Eloquent\Builder;

class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:purge {--dry-run} {--min-age=2y} {--limit=} {--confirm}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users that are inactive';


    private function parseAge($age)
    {
        if (preg_match('/^([0-9]+)([mdy])$/i', $age, $matches)) {
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
            return $date;
        }
        return null;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dry_run = $this->option('dry-run');
        $min_age = $this->option('min-age');
        $limit = $this->option('limit');
        $confirm = $this->option('confirm');

        if (!$confirm && !$dry_run) {
            $this->error("WARNING: THIS COMMAND WILL DELETE USERS, are you sure? Run with --confirm if you are.");
            exit(1);
        }

        $date = $this->parseAge($min_age);
        if (!$date) {
            $this->error("Invalid --min-age.");
            return 1;
        } else {
            $this->info("The cutoff date is " . $date->format('Y-m-d H:i:s'));
        }

        // Find inactive users by checking:
        // * the account is degraded
        // * there is no active oauth token
        // * there is no recent policy_ratelimit entry for the user
        // * there is no recent roundcube login for the user
        $users = User::select('users.id', 'users.email')
            ->leftJoin('oauth_access_tokens', 'oauth_access_tokens.user_id', '=', 'users.id')
            ->leftJoin('policy_ratelimit', 'policy_ratelimit.user_id', '=', 'users.id')
            ->leftJoin('roundcube_prod.users as rcusers', 'rcusers.username', '=', 'users.email')
            ->where('users.status', '&', User::STATUS_DEGRADED)
            ->where('users.updated_at', '<=', $date)
            ->where('users.created_at', '<=', $date)
            ->whereNull('oauth_access_tokens.id')
            ->where(function (Builder $query) use ($date) {
                $query->where('policy_ratelimit.created_at', '<=', $date)
                      ->orWhereNull('policy_ratelimit.id');
            })
            ->where(function (Builder $query) use ($date) {
                $query->where('rcusers.last_login', '<=', $date)
                      ->orWhereNull('rcusers.username');
            });

        if ($limit > 0) {
            $users->limit($limit);
        }

        $users = $users->orderBy('id')->cursor();

        $count = 0;
        foreach ($users as $user) {
            $count++;
            if ($dry_run) {
                $this->info("{$user->email}: will be deleted");
            } else {
                \App\Jobs\User\DeleteJob::dispatch($user->id);
                $this->info("{$user->email}: pushed");
            }
        }
        $this->info("A total of $count users will be deleted");
    }
}
