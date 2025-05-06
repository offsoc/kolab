<?php

namespace App\PowerDNS;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $fillable = [
        'name',
    ];

    protected $table = 'powerdns_domains';

    /**
     * Bump the SOA record serial
     */
    public function bumpSerial(): void
    {
        $soa = $this->records()->where('type', 'SOA')->first();

        [$ns, $hm, $serial, $a, $b, $c, $d] = explode(" ", $soa->content);

        $today = Carbon::now()->format('Ymd');
        $date = substr($serial, 0, 8);

        if ($date != $today) {
            $serial = $today . '01';
        } else {
            $change = (int) substr($serial, 8, 2);

            $serial = sprintf("%s%02s", $date, $change + 1);
        }

        $soa->content = "{$ns} {$hm} {$serial} {$a} {$b} {$c} {$d}";
        $soa->save();
    }

    /**
     * Returns the SOA record serial
     */
    public function getSerial(): string
    {
        $soa = $this->records()->where('type', 'SOA')->first();

        [$ns, $hm, $serial, $a, $b, $c, $d] = explode(" ", $soa->content);

        return $serial;
    }

    /**
     * Any DNS records assigned to this domain.
     *
     * @return HasMany<Record, $this>
     */
    public function records()
    {
        return $this->hasMany(Record::class, 'domain_id');
    }

    /**
     * Any (additional) properties of this domain.
     *
     * @return HasMany<DomainSetting, $this>
     */
    public function settings()
    {
        return $this->hasMany(DomainSetting::class, 'domain_id');
    }
}
