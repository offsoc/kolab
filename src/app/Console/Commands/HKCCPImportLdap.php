<?php

namespace App\Console\Commands;

use App\Backends\LDAP;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;

class HKCCPImportLdap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hkccp:importldap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync LDAP after the migration.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        LDAP::connect();

        $this->syncDomains();
        $this->syncUsers();

        LDAP::disconnect();
    }

    private function syncDomains()
    {
        $domains = \App\Domain::withTrashed()->get();

        $bar = $this->createProgressBar(count($domains), "Syncing Domains");

        foreach ($domains as $domain) {
            $bar->advance();

            $result = null;

            try {
                LDAP::updateDomain($domain);
            } catch (\Exception $e) {
                \Log::error("Domain {$domain->namespace} failed");
                continue;
            }

            \Log::info("Domain {$domain->namespace} updated");
        }

        $bar->finish();

        $this->info("DONE");
    }

    private function syncUsers()
    {
        $users = \App\User::withTrashed()->get();

        $bar = $this->createProgressBar(count($users), "Syncing Users");

        foreach ($users as $user) {
            $bar->advance();

            $result = null;

            try {
                LDAP::updateUser($user);
            } catch (\Exception $e) {
                \Log::error("User {$user->email} failed");
                continue;
            }

            \Log::info("User {$user->email} updated");
        }

        $bar->finish();

        $this->info("DONE");
    }

    private function createProgressBar($count, $message = null)
    {
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(
            '%current:7s%/%max:7s% [%bar%] %percent:3s%% %elapsed:7s%/%estimated:-7s% %message% '
        );

        if ($message) {
            $bar->setMessage($message . " ...");
        }

        $bar->start();

        return $bar;
    }
}
