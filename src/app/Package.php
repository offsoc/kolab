<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

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
 *
 * @property string  $description
 * @property int     $discount_rate
 * @property string  $id
 * @property string  $name
 * @property ?int    $tenant_id
 * @property string  $title
 */
class Package extends Model
{
    use BelongsToTenantTrait;
    use HasTranslations;
    use UuidStrKeyTrait;

    public $timestamps = false;

    protected $fillable = [
        'description',
        'discount_rate',
        'name',
        'title',
    ];

    /** @var array Translatable properties */
    public $translatable = [
        'name',
        'description',
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

    /**
     * Checks whether the package contains a domain SKU.
     */
    public function isDomain(): bool
    {
        foreach ($this->skus as $sku) {
            if ($sku->handler_class::entitleableClass() == \App\Domain::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * SKUs of this package.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
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
