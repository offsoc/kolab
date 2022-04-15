<?php

namespace App\Handlers\Beta;

class SharedFolders extends Base
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
        // This SKU must be:
        // - already assigned, or active and a 'beta' entitlement must exist
        // - and this is a group account owner (custom domain)

        if (parent::isAvailable($sku, $object)) {
            return $object->wallet()->entitlements()
                ->where('entitleable_type', \App\Domain::class)->count() > 0;
        }

        return false;
    }
}
