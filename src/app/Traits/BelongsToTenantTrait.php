<?php

namespace App\Traits;

use App\Tenant;

trait BelongsToTenantTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootBelongsToTenantTrait()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = \config('app.tenant_id');
            }
        });
    }

    /**
     * The tenant for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Tenant, $this>
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
