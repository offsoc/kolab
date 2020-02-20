<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Package.
 *
 * A package is a set of SKUs that a user can select, so that instead of;
 *
 * * Create a mailbox entitlement,
 * * Create a quota entitlement,
 * * Create a groupware entitlement,
 * * ...
 *
 * users can simply select a 'package';
 *
 * * Kolab package: mailbox + quota + groupware,
 * * Free package: mailbox + quota.
 *
 * Selecting a package will therefore create a set of entitlments from SKUs.
 */
class Package extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'discount_rate'
    ];

    public function cost()
    {
        $costs = 0;

        foreach ($this->skus as $sku) {
            $costs += ($sku->pivot->qty - $sku->units_free) * $sku->cost;
        }

        return $costs;
    }

    public function isDomain()
    {
        foreach ($this->skus as $sku) {
            if ($sku->hander_class::entitleableClass() == \App\Domain::class) {
                return true;
            }
        }

        return false;
    }

    public function skus()
    {
        return $this->belongsToMany(
            'App\Sku',
            'package_skus'
        )->using('App\PackageSku')->withPivot(
            ['qty']
        );
    }
}
