<?php

namespace App\Console\Commands\Policy\RateLimit\Whitelist;

use App\Console\Command;
use App\Domain;
use App\Policy\RateLimit\Whitelist;
use App\User;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:ratelimit:whitelist:delete {object}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a policy ratelimit whitelist item';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $object = $this->argument('object');

        if (!str_contains($object, '@')) {
            $domain = $this->getDomain($object);

            if (!$domain) {
                $this->error("No such domain {$object}");
                return 1;
            }

            $id = $domain->id;
            $type = Domain::class;
        } else {
            $user = $this->getUser($object);

            if (!$user) {
                $this->error("No such user {$user}");
                return 1;
            }

            $id = $user->id;
            $type = User::class;
        }

        Whitelist::where(
            [
                'whitelistable_id' => $id,
                'whitelistable_type' => $type,
            ]
        )->delete();
    }
}
