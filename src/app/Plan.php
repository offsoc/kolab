<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Plan.
 *
 * A Plan is a grouping of packages, such as a "Family Plan".
 *
 * A "Family Plan" as such may exist of "2 or more Kolab packages",
 * and apply a discount for the third and further Kolab packages.
 */
class Plan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        // a start and end datetime for this promotion
        'promo_from',
        'promo_to',
        // discounts start at this quantity
        'discount_qty',
        // the rate of the discount for this plan
        'discount_rate',
    ];

    protected $casts = [
        'promo_from' => 'datetime',
        'promo_to' => 'datetime',
        'discount_qty' => 'integer',
        'discount_rate' => 'integer'
    ];

    public function packages()
    {
        return $this->belongsToMany(
            'App\Package',
            'plan_packages'
        )->using('App\PlanPackage')->withPivot(
            [
                'qty_min',
                'qty_max',
                'discount_qty',
                'discount_rate'
            ]
        );
    }
}
