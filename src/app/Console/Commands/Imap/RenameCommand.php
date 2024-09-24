<?php

namespace App\Console\Commands\Imap;

use App\Console\Command;

class RenameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:rename {user} {sourceMailbox} {targetMailbox}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Rename IMAP Folder";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->argument('user');
        $sourceMailbox = $this->argument('sourceMailbox');
        $targetMailbox = $this->argument('targetMailbox');

        \App\Backends\IMAP::renameMailbox(
            self::userMailbox($user, $sourceMailbox),
            self::userMailbox($user, $targetMailbox)
        );
    }
}
