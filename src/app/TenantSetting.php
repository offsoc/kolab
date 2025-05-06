<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['tenant_id', 'key', 'value'];

    /**
     * The tenant to which this setting belongs.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
