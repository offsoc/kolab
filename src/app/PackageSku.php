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
    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'package_id',
        'sku_id',
        'cost',
        'qty'
    ];

    /** @var array<string, string> The attributes that should be cast */
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
        $units = $this->qty - $this->sku->units_free;

        if ($units < 0) {
            $units = 0;
        }

        // FIXME: Why package_skus.cost value is not used anywhere?

        $ppu = $this->sku->cost * ((100 - $this->package->discount_rate) / 100);

        return $units * $ppu;
    }

    /**
     * Under this package, what fee this SKU has?
     *
     * @return int The fee for this SKU under this package in cents.
     */
    public function fee()
    {
        $units = $this->qty - $this->sku->units_free;

        if ($units < 0) {
            $units = 0;
        }

        return $this->sku->fee * $units;
    }

    /**
     * The package for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function package()
    {
        return $this->belongsTo('App\Package');
    }

    /**
     * The SKU for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }
}
