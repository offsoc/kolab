<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * An email address alias for a SharedFolder.
 *
 * @property string $alias
 * @property int    $id
 * @property int    $shared_folder_id
 */
class SharedFolderAlias extends Model
{
    protected $fillable = [
        'shared_folder_id', 'alias'
    ];

    /**
     * Ensure the email address is appropriately cased.
     *
     * @param string $alias Email address
     */
    public function setAliasAttribute(string $alias)
    {
        $this->attributes['alias'] = \App\Utils::emailToLower($alias);
    }

    /**
     * The shared folder to which this alias belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sharedFolder()
    {
        return $this->belongsTo(SharedFolder::class, 'shared_folder_id', 'id');
    }
}
