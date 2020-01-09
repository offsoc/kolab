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
        'key', 'value'
    ];

    /**
     * The user to which this setting belongs.
     *
     * @return \App\User
     */
    public function user()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }
}
