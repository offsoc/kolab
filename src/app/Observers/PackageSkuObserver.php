<?php

namespace App\Observers;

use App\PackageSku;

class PackageSkuObserver
{
    /**
     * Handle the "creating" event on an PackageSku relation.
     *
     * Ensures that the entries belong to the same tenant.
     *
     * @param \App\PackageSku $packageSku The package-sku relation
     *
     * @return void
     */
    public function creating(PackageSku $packageSku)
    {
        $package = $packageSku->package;
        $sku = $packageSku->sku;

        if ($package->tenant_id != $sku->tenant_id) {
            throw new \Exception("Package and SKU owned by different tenants");
        }
    }

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
