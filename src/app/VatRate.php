<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Vat Rate.
 *
 * @property string $id      Rate identifier (uuid)
 * @property string $country Two-letter country code
 * @property float  $rate    Tax rate
 * @property string $start   Start date of the rate
 */
class VatRate extends Model
{
    use UuidStrKeyTrait;

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'start' => 'datetime:Y-m-d H:i:s',
        'rate' => 'float',
    ];

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = [
        'country',
        'rate',
        'start',
    ];

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;
}
