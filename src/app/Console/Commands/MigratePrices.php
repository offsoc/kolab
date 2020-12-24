<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigratePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply a new price list';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->updateSKUs();
        $this->updateEntitlements();
    }

    private function updateSKUs()
    {
        $bar = \App\Utils::createProgressBar($this->output, 8, "Updating SKUs");

        // 1. Set the list price for the SKU 'mailbox' to 500.
        $bar->advance();
        $mailbox_sku = \App\Sku::where('title', 'mailbox')->first();
        $mailbox_sku->cost = 500;
        $mailbox_sku->save();

        // 2. Set the list price for the SKU 'groupware' to 490.
        $bar->advance();
        $groupware_sku = \App\Sku::where('title', 'groupware')->first();
        $groupware_sku->cost = 490;
        $groupware_sku->save();

        // 3. Set the list price for the SKU 'activesync' to 0.
        $bar->advance();
        $activesync_sku = \App\Sku::where('title', 'activesync')->first();
        $activesync_sku->cost = 0;
        $activesync_sku->save();

        // 4. Set the units free for the SKU 'storage' to 5.
        $bar->advance();
        $storage_sku = \App\Sku::where('title', 'storage')->first();
        $storage_sku->units_free = 5;
        $storage_sku->save();

        // 5. Set the number of units for storage to 5 for the 'lite' and 'kolab' packages.
        $bar->advance();
        $kolab_package = \App\Package::where('title', 'kolab')->first();
        $kolab_package->skus()->updateExistingPivot($storage_sku, ['qty' => 5], false);
        $lite_package = \App\Package::where('title', 'lite')->first();
        $lite_package->skus()->updateExistingPivot($storage_sku, ['qty' => 5], false);

        // 6. Set the cost for the 'mailbox' unit for the 'lite' and 'kolab' packages to 500.
        $bar->advance();
        $kolab_package->skus()->updateExistingPivot($mailbox_sku, ['cost' => 500], false);
        $lite_package->skus()->updateExistingPivot($mailbox_sku, ['cost' => 500], false);

        // 7. Set the cost for the 'groupware' unit for the 'kolab' package to 490.
        $bar->advance();
        $kolab_package->skus()->updateExistingPivot($groupware_sku, ['cost' => 490], false);

        // 8. Set the cost for the 'activesync' unit for the 'kolab' package to 0.
        $bar->advance();
        $kolab_package->skus()->updateExistingPivot($activesync_sku, ['cost' => 0], false);

        $bar->finish();

        $this->info("DONE");
    }

    private function updateEntitlements()
    {
        $users = \App\User::all();

        $bar = \App\Utils::createProgressBar($this->output, count($users), "Updating entitlements");

        $groupware_sku = \App\Sku::where('title', 'groupware')->first();
        $activesync_sku = \App\Sku::where('title', 'activesync')->first();
        $storage_sku = \App\Sku::where('title', 'storage')->first();
        $mailbox_sku = \App\Sku::where('title', 'mailbox')->first();

        foreach ($users as $user) {
            $bar->advance();

            // 1. For every user with a mailbox, ensure that there's a minimum of 5 storage entitlements
            //    that are free of charge.
            //    A. For existing storage entitlements reduce the price to 0 until there's 5 of those.
            //    B. Do not touch the entitlement's updated_at column.
            $mailbox = $user->entitlements()->where('sku_id', $mailbox_sku->id)->first();
            if ($mailbox) {
                $storage = $user->entitlements()->where('sku_id', $storage_sku->id)
                    ->orderBy('cost')->orderBy('updated_at')->get();

                $num = 0;
                foreach ($storage as $entitlement) {
                    $num++;
                    if ($num <= 5 && $entitlement->cost) {
                        $entitlement->timestamps = false;
                        $entitlement->cost = 0;
                        $entitlement->save();
                    }
                }

                if ($num < 5) {
                    $user->assignSku($storage_sku, 5 - $num);
                }
            }

            // 2. For every user with a 'groupware' entitlement, set the price of that entitlement to 490
            //    -- without touching updated_at.
            $entitlement = $user->entitlements()->where('sku_id', $groupware_sku->id)->first();
            if ($entitlement) {
                $entitlement->timestamps = false;
                $entitlement->cost = 490;
                $entitlement->save();

                $entitlement = $user->entitlements()->where('sku_id', $mailbox_sku->id)->first();

                if ($entitlement) {
                    $entitlement->timestamps = false;
                    $entitlement->cost = 500;
                    $entitlement->save();
                }
            }

            // 3. For every user with an 'activesync' entitlement, set the price for that entitlement to 0
            //    -- without touching updated_at.
            $entitlement = $user->entitlements()->where('sku_id', $activesync_sku->id)->first();
            if ($entitlement) {
                $entitlement->timestamps = false;
                $entitlement->cost = 0;
                $entitlement->save();
            }
        }

        $bar->finish();

        $this->info("DONE");
    }
}
