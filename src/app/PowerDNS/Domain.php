<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'name'
    ];

    protected $table = 'powerdns_domains';

    public function bumpSerial()
    {
        $soa = $this->records()->where('type', 'SOA')->first();

        list($ns, $hm, $serial, $a, $b, $c, $d) = explode(" ", $soa->content);

        $today = \Carbon\Carbon::now()->format('Ymd');
        $date = substr($serial, 0, 8);

        if ($date != $today) {
            $serial = $today . '01';
        } else {
            $change = (int)(substr($serial, 8, 2));

            $serial = sprintf("%s%02s", $date, ($change + 1));
        }

        $soa->content = "{$ns} {$hm} {$serial} {$a} {$b} {$c} {$d}";
        $soa->save();
    }

    public function getSerial()
    {
        $soa = $this->records()->where('type', 'SOA')->first();

        list($ns, $hm, $serial, $a, $b, $c, $d) = explode(" ", $soa->content);

        return $serial;
    }

    public function records()
    {
        return $this->hasMany('App\PowerDNS\Record', 'domain_id');
    }

    //public function setSerial() { }
}
