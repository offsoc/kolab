<?php

namespace App\Traits;

trait EntitleableTrait
{
    /**
     * Entitlements for this object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany(\App\Entitlement::class, 'entitleable_id', 'id')
            ->where('entitleable_type', self::class);
    }

    /**
     * Returns the wallet by which the object is controlled
     *
     * @return ?\App\Wallet A wallet object
     */
    public function wallet(): ?\App\Wallet
    {
        $entitlement = $this->entitlements()->withTrashed()->orderBy('created_at', 'desc')->first();

        if ($entitlement) {
            return $entitlement->wallet;
        }

        // TODO: No entitlement should not happen, but in tests we have
        //       such cases, so we fallback to the user's wallet in this case
        if ($this instanceof \App\User) {
            return $this->wallets()->first();
        }

        return null;
    }
}
