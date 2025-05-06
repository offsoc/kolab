<?php

namespace App\Traits;

use App\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenantTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootBelongsToTenantTrait()
    {
        static::creating(static function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = \config('app.tenant_id');
            }
        });
    }

    /**
     * The tenant for this model.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
