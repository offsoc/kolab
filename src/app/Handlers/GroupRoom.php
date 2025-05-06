<?php

namespace App\Handlers;

use App\Meet\Room;
use App\Sku;

class GroupRoom extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return Room::class;
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['exclusive'] = ['Room'];
        $data['controllerOnly'] = true;

        return $data;
    }
}
