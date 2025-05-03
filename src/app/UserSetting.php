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

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['user_id', 'key', 'value'];


    /**
     * Check if the setting is used in any storage backend.
     */
    public function isBackendSetting(): bool
    {
        return \config('app.with_ldap')
            && in_array($this->key, ['first_name', 'last_name', 'organization']);
    }
}
