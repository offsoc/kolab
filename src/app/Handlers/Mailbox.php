<?php

namespace App\Handlers;

use App\Sku;
use App\User;

class Mailbox extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return User::class;
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(Sku $sku): array
    {
        $data = parent::metadata($sku);

        // Mailbox is always enabled and cannot be unset
        $data['readonly'] = true;
        $data['enabled'] = true;

        return $data;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        return 100;
    }
}
