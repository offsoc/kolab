<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Tenant.
 *
 * @property int    $id
 * @property string $title
 */
class Tenant extends Model
{
    protected $fillable = [
        'title',
//        'currency'
    ];

    protected $keyType = 'bigint';
}
