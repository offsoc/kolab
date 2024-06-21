<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class DomainSetting extends Model
{
    protected $fillable = [
        'domain_id',
        'kind',
        'content',
    ];

    protected $table = 'powerdns_domain_settings';
}
