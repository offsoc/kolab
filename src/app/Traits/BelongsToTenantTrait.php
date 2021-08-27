<?php

namespace App\Traits;

trait BelongsToTenantTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootBelongsToTenantTrait()
    {
        static::creating(function ($model) {
            $model->tenant_id = \config('app.tenant_id');
        });
    }


    /**
     * The tenant for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo('App\Tenant', 'tenant_id', 'id');
    }
}
