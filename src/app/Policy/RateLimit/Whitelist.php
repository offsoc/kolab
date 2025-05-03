<?php

namespace App\Policy\RateLimit;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a RateLimit Whitelist entry.
 *
 * @property ?object     $whitelistable      The whitelistable object
 * @property int|string  $whitelistable_id   The whitelistable object identifier
 * @property string      $whitelistable_type The whitelistable object type
 */
class Whitelist extends Model
{
    /** @var list<string> The attributes that are mass assignable */
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
