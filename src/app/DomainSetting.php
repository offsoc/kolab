<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
    /** @var string[] The attributes that are mass assignable */
    protected $fillable = ['domain_id', 'key', 'value'];

    /**
     * The domain to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(
            '\App\Domain',
            'domain_id', /* local */
            'id' /* remote */
        );
    }
}
