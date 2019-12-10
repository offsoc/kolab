<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

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

    public function cost()
    {
        $costs = 0;

        if ($this->qty_min > 0) {
            foreach ($this->package->skus() as $sku) {
                $costs += $sku->cost;
            }
        }

        return $costs;
    }
}
