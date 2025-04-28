<?php

namespace App\Console\Commands\Policy\RateLimit\Whitelist;

use Illuminate\Console\Command;

class ReadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:ratelimit:whitelist:read {filter?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read the ratelimit policy whitelist';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \App\Policy\RateLimit\Whitelist::each(
            function ($item) {
                $whitelistable = $item->whitelistable;

                if ($whitelistable instanceof \App\Domain) {
                    $this->info("{$item->id}: {$item->whitelistable_type} {$whitelistable->namespace}");
                } elseif ($whitelistable instanceof \App\User) {
                    $this->info("{$item->id}: {$item->whitelistable_type} {$whitelistable->email}");
                }
            }
        );
    }
}
