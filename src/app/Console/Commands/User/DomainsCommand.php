<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class DomainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:domains {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a user's domains.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        foreach ($user->domains() as $domain) {
            $this->info($domain->namespace);
        }
    }
}
