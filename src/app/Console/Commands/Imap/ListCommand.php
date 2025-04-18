<?php

namespace App\Console\Commands\Imap;

use App\Console\Command;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:list {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List IMAP Folders";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->argument('user');
        foreach (\App\Support\Facades\IMAP::listMailboxes($user) as $mailbox) {
            $this->info("$mailbox");
        }
    }
}
