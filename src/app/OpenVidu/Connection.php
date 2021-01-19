<?php

namespace App\OpenVidu;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Connection.
 *
 * @property string  $id         OpenVidu connection identifier
 * @property array   $metadata   Connection metadata
 * @property int     $role       Connection role
 * @property int     $room_id    Room identifier
 * @property string  $session_id OpenVidu session identifier
 */
class Connection extends Model
{
    protected $table = 'openvidu_connections';

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Dismiss (close) the connection.
     *
     * @return bool True on success, False on failure
     */
    public function dismiss()
    {
        if ($this->room->closeOVConnection($this->id)) {
            $this->delete();

            return true;
        }

        return false;
    }

    /**
     * The room to which this connection belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }
}
