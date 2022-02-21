<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a Group.
 *
 * @property int    $id
 * @property int    $group_id
 * @property string $key
 * @property string $value
 */
class GroupSetting extends Model
{
    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['group_id', 'key', 'value'];

    /**
     * The group to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(\App\Group::class, 'group_id', 'id');
    }
}
