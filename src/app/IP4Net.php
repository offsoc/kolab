<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IP4Net extends Model
{
    protected $table = "ip4nets";

    protected $fillable = [
        'net_number',
        'net_mask',
        'net_broadcast',
        'country',
        'serial'
    ];
}
