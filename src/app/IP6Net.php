<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IP6Net extends Model
{
    protected $table = "ip6nets";

    /** @var string[] The attributes that are mass assignable */
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
        $where = 'INET6_ATON(net_number) <= INET6_ATON(?) and INET6_ATON(net_broadcast) >= INET6_ATON(?)';
        return IP6Net::whereRaw($where, [$ip, $ip])
            ->orderByRaw('INET6_ATON(net_number), net_mask DESC')
            ->first();
    }
}
