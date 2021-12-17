<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\User;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:status {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Show a user's status.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'), true);

        if (!$user) {
            $this->error("User not found.");
            $this->error("Try ./artisan scalpel:user:read --attr=email --attr=tenant_id " . $this->argument('user'));
            return 1;
        }

        $statuses = [
            'active' => User::STATUS_ACTIVE,
            'suspended' => User::STATUS_SUSPENDED,
            'deleted' => User::STATUS_DELETED,
            'ldapReady' => User::STATUS_LDAP_READY,
            'imapReady' => User::STATUS_IMAP_READY,
            'degraded' => User::STATUS_DEGRADED,
        ];

        $user_state = [];

        foreach (\array_keys($statuses) as $state) {
            $func = 'is' . \ucfirst($state);
            if ($user->$func()) {
                $user_state[] = $state;
            }
        }

        $this->info("Status: " . \implode(',', $user_state));
    }
}
