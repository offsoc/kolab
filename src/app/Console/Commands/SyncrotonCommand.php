<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Backends\Roundcube;

class SyncrotonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncroton:inspect {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect the syncroton sync state';

// * INBOX:
// ** imap modseq: <- IMAP
// ** imap message count: <- IMAP
// ** syncroton modseq: <- syncroton_synckey
// ** syncroton synckey: <- syncroton_synckey
// ** syncroton message count: <- syncroton_content
// ** (syncroton missing messages)


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = Roundcube::syncrotonInspect($this->argument('user'));
        $this->info(json_encode($data, JSON_PRETTY_PRINT));
    }
}
