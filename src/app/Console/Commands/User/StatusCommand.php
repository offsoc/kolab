<?php

namespace App\Console\Commands\User;

use Illuminate\Console\Command;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = \App\User::withTrashed()->withEnvTenantContext()->where('email', $this->argument('user'))->first();

        if (!$user) {
            $user = \App\User::withTrashed()->withEnvTenantContext()->where('id', $this->argument('user'))->first();
        }

        if (!$user) {
            $this->error("No such user '" . $this->argument('user') . "' within this tenant context.");
            $this->info("Try ./artisan scalpel:user:read --attr=email --attr=tenant_id " . $this->argument('user'));
            return 1;
        }

        $statuses = [
            'active' => \App\User::STATUS_ACTIVE,
            'suspended' => \App\User::STATUS_SUSPENDED,
            'deleted' => \App\User::STATUS_DELETED,
            'ldapReady' => \App\User::STATUS_LDAP_READY,
            'imapReady' => \App\User::STATUS_IMAP_READY,
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
