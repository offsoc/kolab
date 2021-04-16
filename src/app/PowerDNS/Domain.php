<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'name'
    ];

    protected $table = 'powerdns_domains';
}
