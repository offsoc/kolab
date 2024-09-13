<?php

namespace App\Handlers;

class Resource extends \App\Handlers\Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\Resource::class;
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(\App\Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['readonly'] = true;
        $data['enabled'] = true;

        return $data;
    }
}
