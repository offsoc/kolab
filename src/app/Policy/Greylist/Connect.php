<?php

namespace App\Policy\Greylist;

use Illuminate\Database\Eloquent\Model;

/**
 * @property \App\Domain $domain
 * @property \App\Domain|\App\User $recipient
 * @property \App\User $user
 */
class Connect extends Model
{
    protected $fillable = [
        'sender_local',
        'sender_domain',
        'net_id',
        'net_type',
        'recipient_hash',
        'recipient_id',
        'recipient_type',
        'connect_count',
        'created_at',
        'updated_at'
    ];

    protected $table = 'greylist_connect';

    public function domain()
    {
        if ($this->recipient_type == \App\Domain::class) {
            return $this->recipient;
        }

        return null;
    }

    // determine if the sender is a penpal of the recipient.
    public function isPenpal()
    {
        return false;
    }

    public function user()
    {
        if ($this->recipient_type == \App\User::class) {
            return $this->recipient;
        }

        return null;
    }

    public function net()
    {
        return $this->morphTo();
    }

    public function recipient()
    {
        return $this->morphTo();
    }
}
