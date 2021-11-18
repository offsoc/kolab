<?php

namespace App\Console\Commands\PowerDNS\Domain;

use Illuminate\Console\Command;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'powerdns:domain:create {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a domain in PowerDNS';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('domain');

        if (substr($name, -1) == '.') {
            \Log::error("Domain can not end in '.'");
            return 1;
        }

        if (substr_count($name, '.') < 1) {
            \Log::error("Invalid syntax for a domain.");
            return 1;
        }

        $domain = \App\PowerDNS\Domain::where('name', $name)->first();

        if ($domain) {
            \Log::error("Domain already exists");
            return 1;
        }

        \App\PowerDNS\Domain::create(['name' => $name]);
    }
}
