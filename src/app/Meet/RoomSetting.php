<?php

namespace App\Meet;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a Room.
 *
 * @property int    $id
 * @property int    $room_id
 * @property string $key
 * @property string $value
 */
class RoomSetting extends Model
{
    protected $fillable = [
        'room_id', 'key', 'value'
    ];

    protected $table = 'openvidu_room_settings';

    /**
     * The room to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function room()
    {
        return $this->belongsTo('\App\Meet\Room', 'room_id', 'id');
    }
}
