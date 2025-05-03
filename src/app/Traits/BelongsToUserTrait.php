<?php

namespace App\Traits;

use App\User;

trait BelongsToUserTrait
{
    /**
     * The user to which this object belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
