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

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'description',
        'discount_rate',
        'name',
        'title',
    ];

    /** @var array<int, string> Translatable properties */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * The total monthly costs of this package at either the configured level of the individual
     * SKUs in this package (in the PackageSku table), or the list price PPU for the SKU (free
     * units notwithstanding) with the discount rate for this package applied.
     *
     * NOTE: This results in the overall list price and foregoes additional wallet discount
     * deductions.
     *
     * @return int The costs in cents.
     */
    public function cost()
    {
        $costs = 0;

        foreach ($this->skus as $sku) {
            // Note: This cost already takes package's discount_rate
            $costs += $sku->pivot->cost();
        }

        return $costs;
    }

    /**
     * Checks whether the package contains a domain SKU.
     */
    public function isDomain(): bool
    {
        foreach ($this->skus as $sku) {
            if ($sku->handler_class::entitleableClass() == Domain::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * SKUs of this package.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Sku, $this, PackageSku>
     */
    public function skus()
    {
        return $this->belongsToMany(Sku::class, 'package_skus')
            ->using(PackageSku::class)
            ->withPivot(['qty', 'cost']);
    }
}
