<?php

namespace App\Handlers;

class GroupRoom extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\Meet\Room::class;
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(\App\Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['exclusive'] = ['Room'];
        $data['controllerOnly'] = true;

        return $data;
    }
}
