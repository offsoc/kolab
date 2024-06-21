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
            return 1;
        }

        $statuses = [
            'active' => User::STATUS_ACTIVE,
            'suspended' => User::STATUS_SUSPENDED,
            'deleted' => User::STATUS_DELETED,
            'ldapReady' => User::STATUS_LDAP_READY,
            'imapReady' => User::STATUS_IMAP_READY,
            'degraded' => User::STATUS_DEGRADED,
            'restricted' => User::STATUS_RESTRICTED,
        ];

        $user_state = [];

        foreach ($statuses as $text => $bit) {
            if ($text == 'deleted') {
                $status = $user->trashed();
            } else {
                $status = $user->{'is' . \ucfirst($text)}();
            }

            if ($status) {
                $user_state[] = "$text ($bit)";
            }
        }

        $this->info("Status ({$user->status}): " . \implode(', ', $user_state));
    }
}
