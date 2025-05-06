<?php

namespace App\Meet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['room_id', 'key', 'value'];

    /** @var string Database table name */
    protected $table = 'openvidu_room_settings';

    /**
     * The room to which this setting belongs.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
