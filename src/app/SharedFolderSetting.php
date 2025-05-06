<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collection of settings for a SharedFolder.
 *
 * @property int    $id
 * @property int    $shared_folder_id
 * @property string $key
 * @property string $value
 */
class SharedFolderSetting extends Model
{
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['shared_folder_id', 'key', 'value'];

    /**
     * The folder to which this setting belongs.
     *
     * @return BelongsTo<SharedFolder, $this>
     */
    public function folder()
    {
        return $this->belongsTo(SharedFolder::class, 'shared_folder_id', 'id');
    }

    /**
     * Check if the setting is used in any storage backend.
     */
    public function isBackendSetting(): bool
    {
        return (\config('app.with_imap') || \config('app.with_ldap'))
            && ($this->key == 'acl' || $this->key == 'folder');
    }
}
