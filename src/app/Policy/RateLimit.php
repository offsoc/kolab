<?php

namespace App\Policy;

use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    protected $fillable = [
        'user_id',
        'owner_id',
        'recipient_hash',
        'recipient_count'
    ];

    protected $table = 'policy_ratelimit';

    public function owner()
    {
        $this->belongsTo('App\User');
    }

    public function user()
    {
        $this->belongsTo('App\User');
    }
}
