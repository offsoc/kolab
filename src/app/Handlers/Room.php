<?php

namespace App\Handlers;

class Room extends Base
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

        $data['enabled'] = true;
        $data['exclusive'] = ['GroupRoom'];

        return $data;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        return 10;
    }
}
