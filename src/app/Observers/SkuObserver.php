<?php

namespace App\Observers;

use App\Sku;

class SkuObserver
{
    /**
     * Ensure the SKU ID is a custom ID (uuid).
     *
     * @param Sku $sku
     *
     * @return void
     */
    public function creating(Sku $sku)
    {
        $sku->{$sku->getKeyName()} = \App\Utils::uuidStr();
    }
}
