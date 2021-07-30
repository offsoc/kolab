<?php

namespace App\Handlers\Beta;

class Base extends \App\Handlers\Base
{
    /**
     * Check if the SKU is available to the user.
     *
     * @param \App\Sku  $sku  The SKU object
     * @param \App\User $user The user object
     *
     * @return bool
     */
    public static function isAvailable(\App\Sku $sku, \App\User $user): bool
    {
        // These SKUs must be:
        // 1) already assigned or
        // 2) active and a 'beta' entitlement must exist.

        if ($sku->active) {
            return $user->hasSku('beta');
        } else {
            if ($user->entitlements()->where('sku_id', $sku->id)->first()) {
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

        $data['required'] = ['beta'];

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
