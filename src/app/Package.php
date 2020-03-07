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

    /**
     * The costs of this package at its pre-defined, existing configuration.
     *
     * @return int The costs in cents.
     */
    public function cost()
    {
        $costs = 0;

        foreach ($this->skus as $sku) {
            $units = $sku->pivot->qty - $sku->units_free;

            if ($units < 0) {
                \Log::debug("Package {$this->id} is misconfigured for more free units than qty.");
                $units = 0;
            }

            $ppu = $sku->cost * ((100 - $this->discount_rate) / 100);

            $costs += $units * $ppu;
        }

        return $costs;
    }

    public function isDomain()
    {
        foreach ($this->skus as $sku) {
            if ($sku->handler_class::entitleableClass() == \App\Domain::class) {
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
