<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['group_id', 'key', 'value'];

    /**
     * The group to which this setting belongs.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    /**
     * Check if the setting is used in any storage backend.
     */
    public function isBackendSetting(): bool
    {
        return \config('app.with_ldap') && $this->key == 'sender_policy';
    }
}
