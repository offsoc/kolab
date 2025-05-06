<?php

namespace App\Policy\Greylist;

use Illuminate\Database\Eloquent\Model;

class Whitelist extends Model
{
    protected $table = 'greylist_whitelist';

    protected $fillable = [
        'sender_local',
        'sender_domain',
        'net_id',
        'net_type',
        'created_at',
        'updated_at',
    ];
}
