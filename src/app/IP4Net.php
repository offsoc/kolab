<?php

namespace App;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of an IP network.
 *
 * @property string $country       Country code
 * @property int    $id            Network identifier
 * @property string $net_broadcast Network broadcast address
 * @property string $net_number    Network address
 * @property int    $net_mask      Network mask
 * @property string $rir_name      Network region label
 * @property int    $serial        Serial number
 */
class IP4Net extends Model
{
    /** @var string Database table name */
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
     * @param string $ip IP address
     *
     * @return ?self IP network record, Null if not found
     */
    public static function getNet($ip)
    {
        $ip = inet_pton($ip);

        if (!$ip) {
            return null;
        }

        return static::where('net_number', '<=', $ip)
            ->where('net_broadcast',  '>=', $ip)
            ->orderByRaw('net_number, net_mask DESC')
            ->first();
    }

    /**
     * net_number accessor. Internally we store IP addresses
     * in a numeric form, outside they are human-readable.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function netNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($ip) => inet_ntop($ip),
            set: fn ($ip) => inet_pton($ip),
        );
    }

    /**
     * net_broadcast accessor. Internally we store IP addresses
     * in a numeric form, outside they are human-readable.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function netBroadcast(): Attribute
    {
        return Attribute::make(
            get: fn ($ip) => inet_ntop($ip),
            set: fn ($ip) => inet_pton($ip),
        );
    }
}
