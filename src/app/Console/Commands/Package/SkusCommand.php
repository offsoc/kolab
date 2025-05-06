<?php

namespace App\Console\Commands\Package;

use App\Console\Command;
use App\Package;

class SkusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:skus {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List SKUs for packages.";

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

        $packages = Package::where('tenant_id', $this->tenantId)->get();

        foreach ($packages as $package) {
            $this->info(sprintf("Package: %s", $package->title));

            foreach ($package->skus as $sku) {
                $this->info(sprintf("  SKU: %s (%d)", $sku->title, $sku->pivot->qty));
            }
        }
    }
}
