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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'package_id',
        'sku_id',
        // to set the costs here overrides the sku->cost and package->discount_rate, see function
        // cost() for more detail
        'cost',
        'qty'
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'cost' => 'integer',
        'qty' => 'integer'
    ];

    /** @var list<string> The attributes that can be not set */
    protected $nullable = [
        'cost',
    ];

    /** @var string Database table name */
    protected $table = 'package_skus';

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;


    /**
     * Under this package, how much does this SKU cost?
     *
     * @return int The costs of this SKU under this package in cents.
     */
    public function cost(): int
    {
        $units = $this->qty - $this->sku->units_free;

        if ($units < 0) {
            return 0;
        }

        // one way is to set a very nice looking price in the package_sku->cost
        // this should not be modified by a discount_rate or else there is no purpose to choose
        // that nicely looking pricepoint
        //
        // the other way is to take the sku list price, but sell the package with a percentage
        // discount; this way a nice list price of 1399 with a 15% discount ends up with an "ugly"
        // 1189.15 that needs to be rounded and ends up 1189
        //
        // additional discounts could come from discount vouchers

        // Side-note: Package's discount_rate is on a higher level, so conceptually
        // I wouldn't be surprised if one would expect it to apply to package_sku.cost.

        if ($this->cost !== null) {
            $ppu = $this->cost;
        } else {
            $ppu = round($this->sku->cost * ((100 - $this->package->discount_rate) / 100));
        }

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Package, $this>
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The SKU for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Sku, $this>
     */
    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}
