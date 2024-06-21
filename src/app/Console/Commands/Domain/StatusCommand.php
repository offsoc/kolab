<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use App\Domain;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:status {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Display the status of a domain";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->getDomain($this->argument('domain'), true);

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        $statuses = [
            'active' => Domain::STATUS_ACTIVE,
            'suspended' => Domain::STATUS_SUSPENDED,
            'deleted' => Domain::STATUS_DELETED,
            'confirmed' => Domain::STATUS_CONFIRMED,
            'verified' => Domain::STATUS_VERIFIED,
            'ldapReady' => Domain::STATUS_LDAP_READY,
        ];

        $domain_state = [];

        foreach ($statuses as $text => $bit) {
            if ($text == 'deleted') {
                $status = $domain->trashed();
            } else {
                $status = $domain->{'is' . \ucfirst($text)}();
            }

            if ($status) {
                $domain_state[] = "$text ($bit)";
            }
        }

        $this->info("Status ({$domain->status}): " . \implode(', ', $domain_state));
    }
}
