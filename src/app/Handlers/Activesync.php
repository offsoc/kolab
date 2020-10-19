<?php

namespace App\Handlers;

class Activesync extends \App\Handlers\Base
{
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

        $data['required'] = ['groupware'];

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
        return 70;
    }
}
