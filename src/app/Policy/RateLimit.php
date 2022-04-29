<?php

namespace App\Policy;

use App\Traits\BelongsToUserTrait;
use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    use BelongsToUserTrait;

    protected $fillable = [
        'user_id',
        'owner_id',
        'recipient_hash',
        'recipient_count'
    ];

    protected $table = 'policy_ratelimit';

    public function owner()
    {
        $this->belongsTo(\App\User::class);
    }
}
