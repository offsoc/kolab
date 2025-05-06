<?php

namespace App\Handlers;

use App\Sku;

class SharedFolder extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\SharedFolder::class;
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['readonly'] = true;
        $data['enabled'] = true;

        return $data;
    }
}
