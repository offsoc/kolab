<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\Package;

class PackageSkus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:skus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List SKUs for packages.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $packages = Package::withEnvTenantContext()->get();

        foreach ($packages as $package) {
            $this->info(sprintf("Package: %s", $package->title));

            foreach ($package->skus as $sku) {
                $this->info(sprintf("  SKU: %s (%d)", $sku->title, $sku->pivot->qty));
            }
        }
    }
}
