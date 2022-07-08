<?php

namespace App\Handlers;

abstract class Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        return '';
    }

    /**
     * Check if the SKU is available to the user. An SKU is available
     * to the user/domain when either it is active or there's already an
     * active entitlement.
     *
     * @param \App\Sku  $sku    The SKU
     * @param object    $object The entitleable object
     *
     * @return bool
     */
    public static function isAvailable(\App\Sku $sku, $object): bool
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
     *
     * @param \App\Sku $sku The SKU object
     *
     * @return array
     */
    public static function metadata(\App\Sku $sku): array
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
     *
     * @param \App\Entitlement $entitlement
     * @param mixed $object
     *
     * @return bool
     */
    public static function preReq($entitlement, $object): bool
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
     *
     * @return int
     */
    public static function priority(): int
    {
        return 0;
    }
}
