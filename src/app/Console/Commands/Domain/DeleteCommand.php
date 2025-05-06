<?php

namespace App\Console\Commands\Domain;

use App\Console\ObjectDeleteCommand;
use App\Domain;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = false;
    protected $hidden = false;

    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';

    public function handle()
    {
        $domain = $this->getDomain($this->argument('domain'));

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        if ($domain->isPublic()) {
            $this->error("This domain is a public registration domain.");
            return 1;
        }

        parent::handle();
    }
}
