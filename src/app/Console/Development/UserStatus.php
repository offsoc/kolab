<?php

namespace App\Console\Development;

use App\User;
use Illuminate\Console\Command;

class UserStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:status {userid} {--add=} {--del=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Set/get a user's status.";

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
        $user = User::where('email', $this->argument('userid'))->firstOrFail();

        $this->info("Found user: {$user->id}");

        $statuses = [
            'active' => User::STATUS_ACTIVE,
            'suspended' => User::STATUS_SUSPENDED,
            'deleted' => User::STATUS_DELETED,
            'ldapReady' => User::STATUS_LDAP_READY,
            'imapReady' => User::STATUS_IMAP_READY,
        ];

        // I'd prefer "-state" and "+state" syntax, but it's not possible
        if ($update = $this->option('del')) {
            $delete = true;
        } elseif ($update = $this->option('add')) {
            $delete = false;
        }

        if (!empty($update)) {
            $map = \array_change_key_case($statuses);
            $update = \strtolower($update);

            if (isset($map[$update])) {
                if ($delete && $user->status & $map[$update]) {
                    $user->status ^= $map[$update];
                    $user->save();
                } elseif (!$delete && !($user->status & $map[$update])) {
                    $user->status |= $map[$update];
                    $user->save();
                }
            }
        }

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
