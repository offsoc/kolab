<?php

namespace App\Handlers;

use App\Entitlement;
use App\Sku;
use App\User;

class Mailbox
{
    public static function preReq(Entitlement $entitlement, User $user)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            \Log::info("Sku not active");
            return false;
        }

        list($local, $domain) = explode('@', $user->email);

        $domains = $user->domains();

        foreach ($domains as $_domain) {
            if ($domain == $_domain->namespace) {
                return true;
            }
        }

        \Log::info("Domain not for user");

        return false;
    }
}
