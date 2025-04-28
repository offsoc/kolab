<?php

namespace App\Console\Commands\Policy\RateLimit\Whitelist;

use App\Console\Command;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:ratelimit:whitelist:create {object}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a ratelimit whitelist entry';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $object = $this->argument('object');

        if (strpos($object, '@') === false) {
            $domain = $this->getDomain($object);

            if (!$domain) {
                $this->error("No such domain {$object}");
                return 1;
            }

            $id = $domain->id;
            $type = \App\Domain::class;
        } else {
            $user = $this->getUser($object);

            if (!$user) {
                $this->error("No such user {$user}");
                return 1;
            }

            $id = $user->id;
            $type = \App\User::class;
        }

        \App\Policy\RateLimit\Whitelist::create(
            [
                'whitelistable_id' => $id,
                'whitelistable_type' => $type
            ]
        );
    }
}
