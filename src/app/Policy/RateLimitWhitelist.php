<?php

namespace App\Policy;

use Illuminate\Database\Eloquent\Model;

class RateLimitWhitelist extends Model
{
    protected $fillable = [
        'whitelistable_id',
        'whitelistable_type',
    ];

    protected $table = 'policy_ratelimit_wl';

    /**
     * Principally whitelistable object such as Domain, User.
     *
     * @return mixed
     */
    public function whitelistable()
    {
        return $this->morphTo();
    }
}
