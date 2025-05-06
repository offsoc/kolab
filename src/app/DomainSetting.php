<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collection of settings for a Domain.
 *
 * @property int    $id
 * @property int    $domain_id
 * @property string $key
 * @property string $value
 */
class DomainSetting extends Model
{
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['domain_id', 'key', 'value'];

    /**
     * The domain to which this setting belongs.
     *
     * @return BelongsTo<Domain, $this>
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'id');
    }
}
