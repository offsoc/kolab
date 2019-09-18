<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
    The eloquent definition of an entitlement.

    Owned by a {@link \App\User}, billed to a {@link \App\Wallet}.
 */
class Entitlement extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sku_id',
        'owner_id',
        'user_id',
        'wallet_id',
        'description'
    ];

    /**
        Provide a custom ID (uuid) property.

        @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($entitlement) {
                $entitlement->{$entitlement->getKeyName()} = \App\Utils::uuidStr();

                // Make sure the owner is at least a controller on the wallet
                $owner = \App\User::find($entitlement->owner_id);
                $wallet = \App\Wallet::find($entitlement->wallet_id);

                if (!$owner) {
                    return false;
                }

                if (!$wallet) {
                    return false;
                }

                if (!$wallet->owner() == $owner) {
                    if (!$wallet->controllers->contains($owner)) {
                        return false;
                    }
                }

                $sku = \App\Sku::find($entitlement->sku_id);

                if (!$sku) {
                    return false;
                }

                $wallet->debit($sku->cost);
            }
        );
    }

    /**
        The SKU concerned.

        @return Sku
     */
    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }

    /**
        The owner of this entitlement.

        @return User
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'owner_id');
    }

    /**
        The target user for this entitlement

        @return User
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
        The wallet this entitlement is being billed to

        @return Wallet
     */
    public function wallet()
    {
        return $this->belongsTo('App\Wallet');
    }
}
