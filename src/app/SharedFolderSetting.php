<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
    /** @var string[] The attributes that are mass assignable */
    protected $fillable = ['shared_folder_id', 'key', 'value'];

    /**
     * The folder to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function folder()
    {
        return $this->belongsTo(\App\SharedFolder::class, 'shared_folder_id', 'id');
    }
}
