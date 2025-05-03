<?php

namespace App;

use App\Traits\BelongsToUserTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A email address alias for a User.
 *
 * @property string $alias
 * @property int    $id
 * @property int    $user_id
 */
class UserAlias extends Model
{
    use BelongsToUserTrait;

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['user_id', 'alias'];

    /**
     * Ensure the email address is appropriately cased.
     *
     * @param string $alias Email address
     */
    public function setAliasAttribute(string $alias)
    {
        $this->attributes['alias'] = \strtolower($alias);
    }
}
