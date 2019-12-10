<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

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
