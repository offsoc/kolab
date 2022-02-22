<?php

namespace App;

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
    /** @var bool Indicates if the model should be timestamped. */
    public $timestamps = false;

    /** @var array<string, string> The attributes that should be cast. */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var array<int, string> The attributes that are mass assignable. */
    protected $fillable = ['user_id', 'password'];

    /** @var array<int, string> The attributes that should be hidden for arrays. */
    protected $hidden = ['password'];

    /**
     * The user to which this entry belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('\App\User', 'user_id', 'id');
    }
}
