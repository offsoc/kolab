<?php

namespace App;

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
    use HasTranslations;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'units_free' => 'integer'
    ];

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

    /** @var array Translatable properties */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * List the entitlements that consume this SKU.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
    }

    /**
     * List of packages that use this SKU.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function packages()
    {
        return $this->belongsToMany(
            'App\Package',
            'package_skus'
        )->using('App\PackageSku')->withPivot(['cost', 'qty']);
    }

    /**
     * The tenant for this SKU.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo('App\Tenant', 'tenant_id', 'id');
    }
}
