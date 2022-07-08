<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IP4Net extends Model
{
    protected $table = "ip4nets";

    /** @var array<int, string> The attributes that are mass assignable */
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

    /**
     * Get IP network by IP address
     *
     * @param string $ip IPv4 address
     *
     * @return ?\App\IP4Net IPv4 network record, Null if not found
     */
    public static function getNet($ip)
    {
        $where = 'INET_ATON(net_number) <= INET_ATON(?) and INET_ATON(net_broadcast) >= INET_ATON(?)';

        return self::whereRaw($where, [$ip, $ip])
            ->orderByRaw('INET_ATON(net_number), net_mask DESC')
            ->first();
    }
}
