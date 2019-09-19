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
        'cost' => 'float',
    ];

    protected $fillable = ['title', 'description', 'cost'];

    /**
        List the entitlements that consume this SKU.

        @return Entitlement[]
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
    }
}
