<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class EntitlementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:entitlements {wallet} {--details}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a wallet's entitlements.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wallet = $this->getWallet($this->argument('wallet'));

        if (!$wallet) {
            $this->error("Wallet not found.");
            return 1;
        }

        $details = $this->option('details');

        $discount = $wallet->getDiscountRate();

        $entitlements = $wallet->entitlements()
            ->select('skus.title', 'entitlements.*')
            ->join('skus', 'skus.id', '=', 'entitlements.sku_id')
            ->orderBy('entitleable_type')
            ->orderBy('entitleable_id')
            ->orderBy('sku_id')
            ->orderBy('created_at')
            ->get();

        foreach ($entitlements as $entitlement) {
            // sanity check, deleted entitleable?
            if (!$entitlement->entitleable) {
                $this->info("{$entitlement->id}: DELETED {$entitlement->entitleable_type} {$entitlement->entitleable_id}");
                continue;
            }

            $title = $entitlement->title; // @phpstan-ignore-line
            $cost = $wallet->money((int) ($entitlement->cost * $discount));
            $entitleableTitle = $entitlement->entitleable->toString();
            $add = '';

            if ($details) {
                $add = sprintf(
                    "(created: %s, updated: %s",
                    $entitlement->created_at->toDateString(),
                    $entitlement->updated_at->toDateString()
                );

                if ($discount) {
                    $add .= sprintf(", cost: %s", $wallet->money($entitlement->cost));
                }

                $add .= ")";
            }

            $this->info("{$entitlement->id}: {$entitleableTitle} ({$title}) {$cost}{$add}");
        }
    }
}
