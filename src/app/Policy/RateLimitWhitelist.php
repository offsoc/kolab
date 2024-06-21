<?php

namespace App\Policy;

use Illuminate\Database\Eloquent\Model;

class RateLimitWhitelist extends Model
{
    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'whitelistable_id',
        'whitelistable_type',
    ];

    /** @var string Database table name */
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

    /**
     * Check whether a specified object is whitelisted.
     *
     * @param object $object An object (User, Domain, etc.)
     */
    public static function isListed($object): bool
    {
        return self::where('whitelistable_type', $object::class)
            ->where('whitelistable_id', $object->id)
            ->exists();
    }
}
