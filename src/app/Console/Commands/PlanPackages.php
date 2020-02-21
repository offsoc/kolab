<?php

namespace App\Console\Commands;

use App\Plan;
use Illuminate\Console\Command;

class PlanPackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plan:packages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List packages for plans.";

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
        $plans = Plan::all();

        foreach ($plans as $plan) {
            $this->info(sprintf("Plan: %s", $plan->title));

            $plan_costs = 0;

            foreach ($plan->packages as $package) {
                $qtyMin = $package->pivot->qty_min;
                $qtyMax = $package->pivot->qty_max;

                $discountQty = $package->pivot->discount_qty;
                $discountRate = (100 - $package->pivot->discount_rate) / 100;

                $this->info(
                    sprintf(
                        "  Package: %s (min: %d, max: %d, discount %d%% after the first %d, base cost: %d)",
                        $package->title,
                        $package->pivot->qty_min,
                        $package->pivot->qty_max,
                        $package->pivot->discount_rate,
                        $package->pivot->discount_qty,
                        $package->cost()
                    )
                );

                foreach ($package->skus as $sku) {
                    $this->info(sprintf("    SKU: %s (%d)", $sku->title, $sku->pivot->qty));
                }

                if ($qtyMin < $discountQty) {
                    $plan_costs += $qtyMin * $package->cost();
                } elseif ($qtyMin == $discountQty) {
                    $plan_costs += $package->cost();
                } else {
                    // base rate
                    $plan_costs += $discountQty * $package->cost();

                    // discounted rate
                    $plan_costs += ($qtyMin - $discountQty) * $package->cost() * $discountRate;
                }
            }

            $this->info(sprintf("  Plan costs per month: %d", $plan_costs));
        }
    }
}
