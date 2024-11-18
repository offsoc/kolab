<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Referral (code-to-referree relation).
 *
 * @property string          $code        Referral code
 * @property int             $id          Record identifier
 * @property ?\Carbon\Carbon $redeemed_at When the award got applied
 * @property int             $user_id     User identifier
 */
class Referral extends Model
{
    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'user_id',
        'code',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'redeemed_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /**
     * The code this referral is assigned to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function code()
    {
        return $this->belongsTo(ReferralCode::class, 'code', 'code');
    }

    /**
     * The user this referral is assigned to (referree)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
