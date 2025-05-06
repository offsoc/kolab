<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;
use App\License;

class LicensesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:licenses {type} {file} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports licenses from a file.';

    /** @var bool Adds --tenant option handler */
    protected $withTenant = true;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();

        $file = $this->argument('file');
        $type = $this->argument('type');

        if (!file_exists($file)) {
            $this->error("File '{$file}' does not exist");
            return 1;
        }

        $list = file($file);

        if (empty($list)) {
            $this->error("File '{$file}' is empty");
            return 1;
        }

        $list = array_map('trim', $list);

        $bar = $this->createProgressBar(count($list), "Importing license keys");

        // Import licenses
        foreach ($list as $key) {
            License::create([
                'key' => $key,
                'type' => $type,
                'tenant_id' => $this->tenantId,
            ]);

            $bar->advance();
        }

        $bar->finish();

        $this->info("DONE");
    }
}
