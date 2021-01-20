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

    /**
     * Connection role mutator
     *
     * @throws \Exception
     */
    public function setRoleAttribute($role)
    {
        $new_role = 0;

        $allowed_values = [
            Room::ROLE_SUBSCRIBER,
            Room::ROLE_PUBLISHER,
            Room::ROLE_MODERATOR,
            Room::ROLE_SCREEN,
            Room::ROLE_OWNER,
        ];

        foreach ($allowed_values as $value) {
            if ($role & $value) {
                $new_role |= $value;
                $role ^= $value;
            }
        }

        if ($role > 0) {
            throw new \Exception("Invalid connection role: {$role}");
        }

        $this->attributes['role'] = $new_role;
    }
}
