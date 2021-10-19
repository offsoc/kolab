<?php

namespace App\PowerDNS;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = [
        'name'
    ];

    protected $table = 'powerdns_domains';

    /**
     * Bump the SOA record serial
     */
    public function bumpSerial(): void
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

    /**
     * Returns the SOA record serial
     *
     * @return string
     */
    public function getSerial(): string
    {
        $soa = $this->records()->where('type', 'SOA')->first();

        list($ns, $hm, $serial, $a, $b, $c, $d) = explode(" ", $soa->content);

        return $serial;
    }

    /**
     * Any DNS records assigned to this domain.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function records()
    {
        return $this->hasMany(Record::class, 'domain_id');
    }

    /**
     * Any (additional) properties of this domain.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function settings()
    {
        return $this->hasMany(DomainSetting::class, 'domain_id');
    }
}
