<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a User.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $key
 * @property string $value
 */
class UserSetting extends Model
{
    protected $fillable = [
        'user_id', 'key', 'value'
    ];

    /**
     * The user to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(
            '\App\User',
            'user_id', /* local */
            'id' /* remote */
        );
    }
}
