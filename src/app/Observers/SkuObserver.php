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
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Sku::find($allegedly_unique)) {
                $sku->{$sku->getKeyName()} = $allegedly_unique;
                break;
            }
        }
    }
}
