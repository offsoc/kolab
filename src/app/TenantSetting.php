<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a Tenant.
 *
 * @property int    $id
 * @property string $key
 * @property int    $tenant_id
 * @property string $value
 */
class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'key', 'value'
    ];

    /**
     * The tenant to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo('\App\Tenant', 'tenant_id', 'id');
    }
}
