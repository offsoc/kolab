<?php

namespace App\Console\Commands;

use App\Console\Command;

class DomainListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:list-users {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the user accounts of a domain';

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
        $domain = $this->getDomain($this->argument('domain'));

        if (!$domain) {
            return 1;
        }

        if ($domain->isPublic()) {
            $this->error("This domain is a public registration domain.");
            return 1;
        }

        // TODO: actually implement listing users
        $wallet = $domain->wallet();

        if (!$wallet) {
            $this->error("This domain isn't billed to a wallet.");
            return 1;
        }

        $mailboxSKU = \App\Sku::where('title', 'mailbox')->first();

        if (!$mailboxSKU) {
            $this->error("No mailbox SKU available.");
        }

        $entitlements = $wallet->entitlements()
            ->where('entitleable_type', \App\User::class)
            ->where('sku_id', $mailboxSKU->id)->get();

        $users = [];

        foreach ($entitlements as $entitlement) {
            $users[] = $entitlement->entitleable;
        }

        usort($users, function ($a, $b) {
            return $a->email > $b->email;
        });

        foreach ($users as $user) {
            $this->info($user->email);
        }
    }
}
