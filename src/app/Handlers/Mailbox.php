<?php

namespace App\Handlers;

use App\Entitlement;
use App\Sku;
use App\User;

class Mailbox extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $user)
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }
/*
        FIXME: This code prevents from creating initial mailbox SKU
               on signup of group account, because User::domains()
               does not return the new domain.
               Either we make sure to create domain entitlement before mailbox
               entitlement or make the method here aware of that case or?

        list($local, $domain) = explode('@', $user->email);

        $domains = $user->domains();

        foreach ($domains as $_domain) {
            if ($domain == $_domain->namespace) {
                return true;
            }
        }

        \Log::info("Domain not for user");
*/
        return true;
    }
}
