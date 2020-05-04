<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link SKUs to Packages.
 *
 * @property int          $cost
 * @property string       $package_id
 * @property \App\Package $package
 * @property int          $qty
 * @property \App\Sku     $sku
 * @property string       $sku_id
 */
class PackageSku extends Pivot
{
    protected $fillable = [
        'package_id',
        'sku_id',
        'cost',
        'qty'
    ];

    protected $casts = [
        'cost' => 'integer',
        'qty' => 'integer'
    ];

    /**
     * Under this package, how much does this SKU cost?
     *
     * @return int The costs of this SKU under this package in cents.
     */
    public function cost()
    {
        $costs = 0;

        $units = $this->qty - $this->sku->units_free;

        if ($units < 0) {
            \Log::debug(
                "Package {$this->package_id} is misconfigured for more free units than qty."
            );

            $units = 0;
        }

        $ppu = $this->sku->cost * ((100 - $this->package->discount_rate) / 100);

        $costs += $units * $ppu;

        return $costs;
    }

    public function package()
    {
        return $this->belongsTo('App\Package');
    }

    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }
}
