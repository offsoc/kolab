<?php

namespace App;

use App\Traits\StatusPropertyTrait;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Definition of Delegation (user to user relation).
 *
 * @property int     $delegatee_id
 * @property ?array  $options
 * @property int     $status
 * @property int     $user_id
 */
class Delegation extends Pivot
{
    use StatusPropertyTrait;

    public const STATUS_ACTIVE = 1 << 1;

    /** @var int The allowed states for this object used in StatusPropertyTrait */
    private int $allowed_states = self::STATUS_ACTIVE;

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'status' => 'integer',
        'options' => 'array',
    ];

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'user_id',
        'delegatee_id',
        'options',
    ];

    /** @var list<string> The attributes that can be null */
    protected $nullable = [
        'options',
    ];

    /** @var string Database table name */
    protected $table = 'delegations';

    /** @var bool Enable primary key autoincrement (required here for Pivots) */
    public $incrementing = true;

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;


    /**
     * The delegator user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The delegatee user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function delegatee()
    {
        return $this->belongsTo(User::class, 'delegatee_id');
    }

    /**
     * Validate delegation option value
     *
     * @param string $name  Option name
     * @param mixed  $value Option valie
     */
    public static function validateOption($name, $value): bool
    {
        return in_array($name, ['mail', 'contact', 'event', 'task'])
            && in_array($value, ['read-only', 'read-write']);
    }
}
