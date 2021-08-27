<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IP4Net extends Model
{
    protected $table = "ip4nets";

    protected $fillable = [
        'rir_name',
        'net_number',
        'net_mask',
        'net_broadcast',
        'country',
        'serial',
        'created_at',
        'updated_at'
    ];

    public static function getNet($ip)
    {
        $where = 'INET_ATON(net_number) <= INET_ATON(?) and INET_ATON(net_broadcast) >= INET_ATON(?)';
        return IP4Net::whereRaw($where, [$ip, $ip])
            ->orderByRaw('INET_ATON(net_number), net_mask DESC')
            ->first();
    }
}
