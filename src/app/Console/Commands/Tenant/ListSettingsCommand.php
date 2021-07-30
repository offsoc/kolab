<?php

namespace App\Console\Commands\Tenant;

use App\Console\Command;

class ListSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:list-settings {tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List settings for the tenant.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tenant = $this->getObject(\App\Tenant::class, $this->argument('tenant'), 'title');

        if (!$tenant) {
            $this->error("Unable to find the tenant.");
            return 1;
        }

        $tenant->settings()->orderBy('key')->get()
            ->each(function ($entry) {
                $text = "{$entry->key}: {$entry->value}";
                $this->info($text);
            });
    }
}
