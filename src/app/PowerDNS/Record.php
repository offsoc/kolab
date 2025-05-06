<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $fillable = [
        'domain_id',
        'name',
        'type',
        'content',
    ];

    protected $table = 'powerdns_records';

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'id');
    }

    public function toString()
    {
        return "{$this->name}. {$this->type} {$this->content}";
    }
}
