<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Tenant.
 *
 * @property int    $id
 * @property string $title
 */
class Tenant extends Model
{
    protected $fillable = [
        'title',
    ];

    protected $keyType = 'bigint';

    /**
     * Discounts assigned to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function discounts()
    {
        return $this->hasMany('App\Discount');
    }

    /**
     * SignupInvitations assigned to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function signupInvitations()
    {
        return $this->hasMany('App\SignupInvitation');
    }

    /*
     * Returns the wallet of the tanant (reseller's wallet).
     *
     * @return ?\App\Wallet A wallet object
     */
    public function wallet(): ?Wallet
    {
        $user = \App\User::where('role', 'reseller')->where('tenant_id', $this->id)->first();

        return $user ? $user->wallets->first() : null;
    }
}
