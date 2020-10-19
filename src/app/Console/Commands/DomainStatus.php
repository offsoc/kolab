<?php

namespace App\Console\Commands;

use App\Domain;
use Illuminate\Console\Command;

class DomainStatus extends Command
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
    protected $description = 'Display the status of a domain';

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
        $domain = Domain::where('namespace', $this->argument('domain'))->first();

        if (!$domain) {
            return 1;
        }

        $statuses = [
            'active' => Domain::STATUS_ACTIVE,
            'suspended' => Domain::STATUS_SUSPENDED,
            'deleted' => Domain::STATUS_DELETED,
            'ldapReady' => Domain::STATUS_LDAP_READY,
            'verified' => Domain::STATUS_VERIFIED,
            'confirmed' => Domain::STATUS_CONFIRMED,
        ];

        foreach ($statuses as $text => $bit) {
            $func = 'is' . \ucfirst($text);

            $this->info(sprintf("%d %s: %s", $bit, $text, $domain->$func()));
        }

        $this->info("In total: {$domain->status}");
    }
}
