<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\User;

class UserStatus extends Command
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
    protected $description = 'Display the status of a user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            return 1;
        }

        $statuses = [
            'active' => User::STATUS_ACTIVE,
            'suspended' => User::STATUS_SUSPENDED,
            'deleted' => User::STATUS_DELETED,
            'ldapReady' => User::STATUS_LDAP_READY,
            'imapReady' => User::STATUS_IMAP_READY,
        ];

        foreach ($statuses as $text => $bit) {
            $func = 'is' . \ucfirst($text);

            $this->info(sprintf("%d %s: %s", $bit, $text, $user->$func()));
        }

        $this->info("In total: {$user->status}");
    }
}
