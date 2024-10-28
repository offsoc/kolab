<?php

namespace App\Console\Commands\Imap;

use App\Backends\IMAP;
use App\Console\Command;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:delete {user} {mailbox} {--clear : Clear mailbox instead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete IMAP Folders";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->argument('user');
        $mailbox = $this->argument('mailbox');
        if ($mailbox == "*") {
            // Reverse so subfolders are deleted before parent folders
            foreach (array_reverse(IMAP::listMailboxes($user)) as $mailbox) {
                if ($this->option('clear')) {
                    IMAP::clearMailbox($mailbox);
                } else {
                    IMAP::deleteMailbox($mailbox);
                }
            }
            // Can't delete INBOX
            IMAP::clearMailbox(IMAP::userMailbox($user, "INBOX"));
        } else {
            if ($this->option('clear')) {
                IMAP::clearMailbox(IMAP::userMailbox($user, $mailbox));
            } else {
                IMAP::deleteMailbox(IMAP::userMailbox($user, $mailbox));
            }
        }
    }
}
