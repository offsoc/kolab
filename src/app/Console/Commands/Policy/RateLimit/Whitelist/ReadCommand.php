<?php

namespace App\Console\Commands\Policy\RateLimit\Whitelist;

use App\Domain;
use App\Policy\RateLimit\Whitelist;
use App\User;
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
        Whitelist::each(
            function ($item) {
                $whitelistable = $item->whitelistable;

                if ($whitelistable instanceof Domain) {
                    $this->info("{$item->id}: {$item->whitelistable_type} {$whitelistable->namespace}");
                } elseif ($whitelistable instanceof User) {
                    $this->info("{$item->id}: {$item->whitelistable_type} {$whitelistable->email}");
                }
            }
        );
    }
}
