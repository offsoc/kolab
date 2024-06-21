<?php

namespace App;

use App\Traits\BelongsToUserTrait;
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
    use BelongsToUserTrait;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['user_id', 'key', 'value'];
}
