<?php

namespace App;

use App\Traits\BelongsToUserTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A password history entry of a User.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $password
 */
class UserPassword extends Model
{
    use BelongsToUserTrait;

    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /** @var array<string, string> The attributes that should be cast. */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var list<string> The attributes that are mass assignable. */
    protected $fillable = ['user_id', 'password'];

    /** @var list<string> The attributes that should be hidden for arrays. */
    protected $hidden = ['password'];
}
