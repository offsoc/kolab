<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The eloquent definition of a Plan.
 *
 * A Plan is a grouping of packages, such as a "Family Plan".
 *
 * A "Family Plan" as such may exist of "2 or more Kolab packages",
 * and apply a discount for the third and further Kolab packages.
 *
 * @property string         $description
 * @property int            $discount_qty
 * @property int            $discount_rate
 * @property string         $id
 * @property string         $name
 * @property \App\Package[] $packages
 * @property datetime       $promo_from
 * @property datetime       $promo_to
 * @property ?int           $tenant_id
 * @property string         $title
 */
class Plan extends Model
{
    use HasTranslations;
    use UuidStrKeyTrait;

    public $timestamps = false;

    protected $fillable = [
        'title',
        'name',
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

    /** @var array Translatable properties */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * The list price for this package at the minimum configuration.
     *
     * @return int The costs in cents.
     */
    public function cost()
    {
        $costs = 0;

        foreach ($this->packages as $package) {
            $costs += $package->pivot->cost();
        }

        return $costs;
    }

    /**
     * The relationship to packages.
     *
     * The plan contains one or more packages. Each package may have its minimum number (for
     * billing) or its maximum (to allow topping out "enterprise" customers on a "small business"
     * plan).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function packages()
    {
        return $this->belongsToMany(
            'App\Package',
            'plan_packages'
        )->using('App\PlanPackage')->withPivot(
            [
                'qty',
                'qty_min',
                'qty_max',
                'discount_qty',
                'discount_rate'
            ]
        );
    }

    /**
     * Checks if the plan has any type of domain SKU assigned.
     *
     * @return bool
     */
    public function hasDomain(): bool
    {
        foreach ($this->packages as $package) {
            if ($package->isDomain()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The tenant for this plan.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo('App\Tenant', 'tenant_id', 'id');
    }
}
