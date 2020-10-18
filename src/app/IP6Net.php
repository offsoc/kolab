<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IP6Net extends Model
{
    protected $table = "ip6nets";

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

    public static function getNet($ip, $mask = 128)
    {
        $query =  "
            SELECT id FROM ip6nets
            WHERE INET6_ATON(net_number) <= INET6_ATON(?)
            AND INET6_ATON(net_broadcast) >= INET6_ATON(?)
            ORDER BY INET6_ATON(net_number), net_mask DESC LIMIT 1
        ";

        $results = DB::select($query, [$ip, $ip]);

        if (sizeof($results) == 0) {
            return null;
        }

        return \App\IP6Net::find($results[0]->id);
    }
}
