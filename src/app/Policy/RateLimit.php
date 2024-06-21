<?php

namespace App\Policy;

use App\Traits\BelongsToUserTrait;
use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    use BelongsToUserTrait;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'user_id',
        'owner_id',
        'recipient_hash',
        'recipient_count'
    ];

    /** @var string Database table name */
    protected $table = 'policy_ratelimit';
}
