<?php

namespace App\Handlers;

class Resource extends \App\Handlers\Base
{
    public static function entitleableClass(): string
    {
        // TODO
        return '';
    }

    public static function preReq($entitlement, $owner): bool
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
