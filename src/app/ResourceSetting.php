<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a Resource.
 *
 * @property int    $id
 * @property int    $resource_id
 * @property string $key
 * @property string $value
 */
class ResourceSetting extends Model
{
    protected $fillable = [
        'resource_id', 'key', 'value'
    ];

    /**
     * The resource to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function resource()
    {
        return $this->belongsTo(\App\Resource::class, 'resource_id', 'id');
    }
}
