<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link SKUs to Packages.
 */
class PackageSku extends Pivot
{
    protected $fillable = [
        'package_id',
        'sku_id',
        'qty'
    ];

    protected $casts = [
        'qty' => 'integer'
    ];
}
