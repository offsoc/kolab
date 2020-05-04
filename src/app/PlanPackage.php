<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link Packages to Plans.
 *
 * @property int          $discount_qty
 * @property int          $discount_rate
 * @property string       $plan_id
 * @property string       $package_id
 * @property int          $qty
 * @property int          $qty_max
 * @property int          $qty_min
 * @property \App\Package $package
 */
class PlanPackage extends Pivot
{
    protected $fillable = [
        'plan_id',
        'package_id',
        'qty',
        'qty_max',
        'qty_min',
        'discount_qty',
        'discount_rate'
    ];

    protected $casts = [
        'qty' => 'integer',
        'qty_max' => 'integer',
        'qty_min' => 'integer',
        'discount_qty' => 'integer',
        'discount_rate' => 'integer'
    ];

    /**
     * Calculate the costs for this plan.
     *
     * @return integer
     */
    public function cost()
    {
        $costs = 0;

        if ($this->qty_min > 0) {
            $costs += $this->package->cost() * $this->qty_min;
        } elseif ($this->qty > 0) {
            $costs += $this->package->cost() * $this->qty;
        }

        return $costs;
    }

    public function package()
    {
        return $this->belongsTo('App\Package');
    }
}
