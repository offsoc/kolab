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

    public static function getNet($ip, $mask = 32)
    {
        $query =  "
            SELECT id FROM ip4nets
            WHERE INET_ATON(net_number) <= INET_ATON(?)
            AND INET_ATON(net_broadcast) >= INET_ATON(?)
            ORDER BY INET_ATON(net_number), net_mask DESC LIMIT 1
        ";

        $results = DB::select($query, [$ip, $ip]);

        if (sizeof($results) == 0) {
            return null;
        }

        return \App\IP4Net::find($results[0]->id);
    }
}
