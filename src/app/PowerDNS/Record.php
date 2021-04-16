<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $fillable = [
        'domain_id',
        'name',
        'type',
        'content'
    ];

    protected $table = 'powerdns_records';
}
