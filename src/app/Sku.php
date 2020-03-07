<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Stock Keeping Unit (SKU).
 */
class Sku extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'units_free' => 'integer'
    ];

    protected $fillable = [
        'title',
        'description',
        'cost',
        'units_free',
        // persist for annual domain registration
        'period',
        'handler_class',
        'active'
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

    public function packages()
    {
        return $this->belongsToMany(
            'App\Package',
            'package_skus'
        )->using('App\PackageSku')->withPivot(['cost', 'qty']);
    }
}
