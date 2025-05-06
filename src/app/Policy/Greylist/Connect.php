<?php

namespace App\Policy\Greylist;

use App\Domain;
use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Domain      $domain
 * @property Domain|User $recipient
 * @property User        $user
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
        'updated_at',
    ];

    protected $table = 'greylist_connect';

    public function domain()
    {
        if ($this->recipient_type == Domain::class) {
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
        if ($this->recipient_type == User::class) {
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
