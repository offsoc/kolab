<?php

namespace App\Observers;

use App\Sku;

class SkuObserver
{
    /**
     * Ensure the SKU ID is a custom ID (uuid).
     *
     * @param Sku $sku The SKU object
     *
     * @return void
     */
    public function creating(Sku $sku)
    {
        $sku->tenant_id = \config('app.tenant_id');
    }
}
