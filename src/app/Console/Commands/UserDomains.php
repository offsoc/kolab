<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:domains {userid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('userid'));

        if (!$user) {
            return 1;
        }

        foreach ($user->domains() as $domain) {
            $this->info("{$domain->namespace}");
        }
    }
}
