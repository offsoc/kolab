<?php

namespace App\OpenVidu;

use App\Traits\OpenVidu\RoomSettingsTrait;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use RoomSettingsTrait;

    protected $fillable = [
        'user_id',
        'session_id'
    ];

    protected $table = 'openvidu_rooms';
}
