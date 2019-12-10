<?php

namespace App\Console\Commands;

use App\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PackageSkusCommand extends Command
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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $packages = Package::get();

        foreach ($packages as $package) {
            $this->info(sprintf("Package: %s", $package->title));

            foreach ($package->skus()->get() as $sku) {
                $this->info(sprintf("  SKU: %s (%d)", $sku->title, $sku->pivot->qty));
            }
        }
    }
}
