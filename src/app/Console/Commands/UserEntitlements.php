<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserEntitlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:entitlements {userid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a user's entitlements.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('userid'));

        if (!$user) {
            return 1;
        }

        $this->info("Found user: {$user->id}");

        $skus_counted = [];

        foreach ($user->entitlements as $entitlement) {
            if (!array_key_exists($entitlement->sku_id, $skus_counted)) {
                $skus_counted[$entitlement->sku_id] = 1;
            } else {
                $skus_counted[$entitlement->sku_id] += 1;
            }
        }

        foreach ($skus_counted as $id => $qty) {
            $sku = \App\Sku::find($id);
            $this->info("SKU: {$sku->title} ({$qty})");
        }
    }
}
