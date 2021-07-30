<?php

namespace App\Console\Commands\Domain;

use App\Console\ObjectDeleteCommand;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = false;
    protected $hidden = false;

    protected $objectClass = \App\Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';

    public function handle()
    {
        $argument = $this->argument('domain');

        $domain = $this->getDomain($argument);

        if (!$domain) {
            $this->error("No such domain {$argument}");
            return 1;
        }

        if ($domain->isPublic()) {
            $this->error("This domain is a public registration domain.");
            return 1;
        }

        parent::handle();
    }
}
