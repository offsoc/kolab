<?php

namespace App\Handlers;

use App\Entitlement;
use App\Sku;

abstract class Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return '';
    }

    /**
     * Handle entitlement creation event.
     */
    public static function entitlementCreated(Entitlement $entitlement): void
    {
        // NOP
    }

    /**
     * Handle entitlement deletion event.
     */
    public static function entitlementDeleted(Entitlement $entitlement): void
    {
        // NOP
    }

    /**
     * Check if the SKU is available to the user. An SKU is available
     * to the user/domain when either it is active or there's already an
     * active entitlement.
     */
    public static function isAvailable(Sku $sku, $object): bool
    {
        if (!$sku->active) {
            if (!$object->entitlements()->where('sku_id', $sku->id)->first()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Metadata of this SKU handler.
     */
    public static function metadata(Sku $sku): array
    {
        return [
            // entitleable type
            'type' => \lcfirst(\class_basename(static::entitleableClass())),
            // handler
            'handler' => str_replace("App\\Handlers\\", '', static::class),
            // readonly entitlement state cannot be changed
            'readonly' => false,
            // is entitlement enabled by default?
            'enabled' => false,
            // priority on the entitlements list
            'prio' => static::priority(),
        ];
    }

    /**
     * Prerequisites for the Entitlement to be applied to the object.
     */
    public static function preReq(Entitlement $entitlement, $object): bool
    {
        $type = static::entitleableClass();

        if (empty($type) || empty($entitlement->entitleable_type)) {
            \Log::error("Entitleable class/type not specified");
            return false;
        }

        if ($type !== $entitlement->entitleable_type) {
            \Log::error("Entitleable class mismatch");
            return false;
        }

        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        return 0;
    }
}
