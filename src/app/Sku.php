<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Stock Keeping Unit (SKU).
 */
class Sku extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'units_free' => 'integer'
    ];

    protected $fillable = [
        'title',
        'description',
        'cost',
        'units_free',
        'period',
        'handler_class',
        'active'
    ];

    /**
     * List the entitlements that consume this SKU.
     *
     * @return Entitlement[]
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
    }

    public function packages()
    {
        return $this->belongsToMany(
            'App\Package',
            'package_skus'
        )->using('App\PackageSku')->withPivot(['qty']);
    }

    /**
     * Register (default) SKU entitlement for specified user.
     * This method should be used e.g. on user creation when we have
     * a set of SKUs and want to create entitlements for them (using
     * default values).
     */
    public function registerEntitlement(\App\User $user, array $params = [])
    {
        if (!$this->active) {
            \Log::debug("Skipped registration of an entitlement for non-active SKU ($this->title)");
            return;
        }

        $wallet = $user->wallets()->get()[0];

        $entitlement = new \App\Entitlement();
        $entitlement->owner_id = $user->id;
        $entitlement->wallet_id = $wallet->id;
        $entitlement->sku_id = $this->id;

        $entitlement->entitleable_type = $this->handler_class::entitleableClass();

        if ($user instanceof $entitlement->entitleable_type) {
            $entitlement->entitleable_id = $user->id;
        } else {
            foreach ($params as $param) {
                if ($param instanceof $entitlement->entitleable_type) {
                    $entitlement->entitleable_id = $param->id;
                    break;
                }
            }
        }

        if (empty($entitlement->entitleable_id)) {
            if (method_exists($this->handler_class, 'createDefaultEntitleable')) {
                $entitlement->entitleable_id = $this->handler_class::createDefaultEntitleable($user);
            } else {
                throw new Exception("Failed to create an entitlement for SKU ($this->title). Missing entitleable_id.");
            }
        }

        $entitlement->save();
    }
}
