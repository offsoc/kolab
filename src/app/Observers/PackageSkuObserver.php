<?php

namespace App\Observers;

use App\PackageSku;

class PackageSkuObserver
{
    /**
     * Handle the "created" event on an PackageSku relation
     *
     * @param \App\PackageSku $packageSku The package-sku relation
     *
     * @return void
     */
    public function created(PackageSku $packageSku)
    {
        // TODO: free units...
        $package = $packageSku->package;
        $sku = $packageSku->sku;

        $package->skus()->updateExistingPivot(
            $sku,
            ['cost' => ($sku->cost * (100 - $package->discount_rate)) / 100],
            false
        );
    }
}
