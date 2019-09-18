<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'key', 'value'
    ];

    public function user()
    {
        return $this->belongsTo('\App\User', 'id', 'user_id');
    }
}
