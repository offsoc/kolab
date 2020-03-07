<?php

namespace App\Observers;

class PackageSkuObserver
{
    public function created(\App\PackageSku $packageSku)
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
