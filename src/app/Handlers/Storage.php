<?php

namespace App\Handlers;

use App\Entitlement;
use App\Jobs\User\UpdateJob;
use App\Sku;
use App\User;

class Storage extends Base
{
    public const MAX_ITEMS = 100;
    public const ITEM_UNIT = 'GB';

    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return User::class;
    }

    /**
     * Handle entitlement creation event.
     */
    public static function entitlementCreated(Entitlement $entitlement): void
    {
        // Update the user IMAP mailbox quota
        UpdateJob::dispatch($entitlement->entitleable_id);
    }

    /**
     * Handle entitlement deletion event.
     */
    public static function entitlementDeleted(Entitlement $entitlement): void
    {
        // Update the user IMAP mailbox quota
        UpdateJob::dispatch($entitlement->entitleable_id);
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(Sku $sku): array
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
     */
    public static function priority(): int
    {
        return 90;
    }
}
