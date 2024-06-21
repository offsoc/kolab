<?php

namespace App\Console\Development;

use App\Domain;
use Illuminate\Console\Command;

class DomainStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:status {domain} {--add=} {--del=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Set/get a domain's status.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = Domain::where('namespace', $this->argument('domain'))->firstOrFail();

        $statuses = [
            'active' => Domain::STATUS_ACTIVE,
            'suspended' => Domain::STATUS_SUSPENDED,
            'deleted' => Domain::STATUS_DELETED,
            'ldapReady' => Domain::STATUS_LDAP_READY,
            'verified' => Domain::STATUS_VERIFIED,
            'confirmed' => Domain::STATUS_CONFIRMED,
        ];

        // I'd prefer "-state" and "+state" syntax, but it's not possible
        $delete = false;
        if ($update = $this->option('del')) {
            $delete = true;
        } elseif ($update = $this->option('add')) {
            // do nothing
        }

        if (!empty($update)) {
            $map = \array_change_key_case($statuses);
            $update = \strtolower($update);

            if (isset($map[$update])) {
                if ($delete && $domain->status & $map[$update]) {
                    $domain->status ^= $map[$update];
                    $domain->save();
                } elseif (!$delete && !($domain->status & $map[$update])) {
                    $domain->status |= $map[$update];
                    $domain->save();
                }
            }
        }

        $domain_state = [];
        foreach (\array_keys($statuses) as $state) {
            $func = 'is' . \ucfirst($state);
            if ($domain->$func()) {
                $domain_state[] = $state;
            }
        }

        $this->info("Status: " . \implode(',', $domain_state));
    }
}
