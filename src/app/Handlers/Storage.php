<?php

namespace App\Handlers;

class Storage extends \App\Handlers\Base
{
    public const MAX_ITEMS = 100;
    public const ITEM_UNIT = 'GB';

    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    /**
     * SKU handler metadata.
     *
     * @param \App\Sku $sku The SKU object
     *
     * @return array
     */
    public static function metadata(\App\Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['readonly'] = true; // only the checkbox will be disabled, not range
        $data['enabled'] = true;
        $data['range'] = [
           'min' => $sku->units_free,
           'max' => self::MAX_ITEMS,
           'unit' => self::ITEM_UNIT,
        ];

        return $data;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     *
     * @return int
     */
    public static function priority(): int
    {
        return 90;
    }
}
