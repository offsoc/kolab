<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\BelongsToUserTrait;
use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a signup invitation.
 *
 * @property string $email
 * @property string $id
 * @property ?int   $tenant_id
 * @property ?int   $user_id
 */
class SignupInvitation extends Model
{
    use BelongsToTenantTrait;
    use BelongsToUserTrait;
    use UuidStrKeyTrait;

    // just created
    public const STATUS_NEW = 1 << 0;
    // it's been sent successfully
    public const STATUS_SENT = 1 << 1;
    // sending failed
    public const STATUS_FAILED = 1 << 2;
    // the user signed up
    public const STATUS_COMPLETED = 1 << 3;

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['email'];

    /**
     * Returns whether this invitation process completed (user signed up)
     */
    public function isCompleted(): bool
    {
        return ($this->status & self::STATUS_COMPLETED) > 0;
    }

    /**
     * Returns whether this invitation sending failed.
     */
    public function isFailed(): bool
    {
        return ($this->status & self::STATUS_FAILED) > 0;
    }

    /**
     * Returns whether this invitation is new.
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this invitation has been sent.
     */
    public function isSent(): bool
    {
        return ($this->status & self::STATUS_SENT) > 0;
    }
}
