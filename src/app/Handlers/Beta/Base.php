<?php

namespace App\Handlers\Beta;

class Base extends \App\Handlers\Base
{
    /**
     * Check if the SKU is available to the user/domain.
     *
     * @param \App\Sku              $sku    The SKU object
     * @param \App\User|\App\Domain $object The user or domain object
     *
     * @return bool
     */
    public static function isAvailable(\App\Sku $sku, $object): bool
    {
        // These SKUs must be:
        // 1) already assigned or
        // 2) active and a 'beta' entitlement must exist.

        if (!$object instanceof \App\User) {
            return false;
        }

        if ($sku->active) {
            return $object->hasSku('beta');
        } else {
            if ($object->entitlements()->where('sku_id', $sku->id)->first()) {
                return true;
            }
        }

        return false;
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

        $data['required'] = ['Beta'];

        return $data;
    }

    /**
     * Prerequisites for the Entitlement to be applied to the object.
     *
     * @param \App\Entitlement $entitlement
     * @param mixed            $object
     *
     * @return bool
     */
    public static function preReq($entitlement, $object): bool
    {
        if (!parent::preReq($entitlement, $object)) {
            return false;
        }

        // TODO: User has to have the "beta" entitlement

        return true;
    }
}
