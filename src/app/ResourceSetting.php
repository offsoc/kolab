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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['resource_id', 'key', 'value'];

    /**
     * The resource to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Resource, $this>
     */
    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }

    /**
     * Check if the setting is used in any storage backend.
     */
    public function isBackendSetting(): bool
    {
        return (\config('app.with_imap') || \config('app.with_ldap'))
            && ($this->key == 'invitation_policy' || $this->key == 'folder');
    }
}
