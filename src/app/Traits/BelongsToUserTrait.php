<?php

namespace App\Traits;

trait BelongsToUserTrait
{
    /**
     * The user to which this object belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}
