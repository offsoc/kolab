<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The eloquent definition of a Stock Keeping Unit (SKU).
 *
 * @property bool    $active
 * @property int     $cost
 * @property string  $description
 * @property int     $fee           The fee that the tenant pays to us
 * @property string  $handler_class
 * @property string  $id
 * @property string  $name
 * @property string  $period
 * @property ?int    $tenant_id
 * @property string  $title
 * @property int     $units_free
 */
class Sku extends Model
{
    use BelongsToTenantTrait;
    use HasTranslations;
    use UuidStrKeyTrait;

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'units_free' => 'integer'
    ];

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'active',
        'cost',
        'description',
        'fee',
        'handler_class',
        'name',
        // persist for annual domain registration
        'period',
        'title',
        'units_free',
    ];

    /** @var array<int, string> Translatable properties */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * List the entitlements that consume this SKU.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Entitlement, $this>
     */
    public function entitlements()
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * List of packages that use this SKU.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Package, $this, PackageSku>
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_skus')
            ->using(PackageSku::class)
            ->withPivot(['cost', 'qty']);
    }
}
