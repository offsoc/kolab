<?php

namespace App;

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
    /** @var array<int, string> The attributes that are mass assignable */
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

    /**
     * The user to which this alias belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
