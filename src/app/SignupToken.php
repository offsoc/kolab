<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The eloquent definition of a SignupToken.
 *
 * @property Carbon  $created_at The creation timestamp
 * @property int     $counter    Count of signups on this token
 * @property ?string $id         Token
 * @property ?string $plan_id    Plan identifier
 */
class SignupToken extends Model
{
    /** @var bool Indicates if the IDs are auto-incrementing */
    public $incrementing = false;

    /** @var string The "type" of the auto-incrementing ID */
    protected $keyType = 'string';

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'plan_id',
        'id',
        'counter',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'counter' => 'integer',
    ];

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /**
     * The plan this token applies to
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
