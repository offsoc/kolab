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
    ];

    protected $keyType = 'bigint';

    /**
     * Discounts assigned to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function discounts()
    {
        return $this->hasMany('App\Discount');
    }
}
