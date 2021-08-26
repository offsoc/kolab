<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenantTrait;
use App\Traits\UuidStrKeyTrait;

/**
 * The eloquent definition of a signup invitation.
 *
 * @property string       $email
 * @property string       $id
 * @property ?int         $tenant_id
 * @property ?\App\Tenant $tenant
 * @property ?\App\User   $user
 */
class SignupInvitation extends Model
{
    use BelongsToTenantTrait;
    use UuidStrKeyTrait;

    // just created
    public const STATUS_NEW     = 1 << 0;
    // it's been sent successfully
    public const STATUS_SENT    = 1 << 1;
    // sending failed
    public const STATUS_FAILED  = 1 << 2;
    // the user signed up
    public const STATUS_COMPLETED = 1 << 3;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['email'];

    /**
     * Returns whether this invitation process completed (user signed up)
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return ($this->status & self::STATUS_COMPLETED) > 0;
    }

    /**
     * Returns whether this invitation sending failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return ($this->status & self::STATUS_FAILED) > 0;
    }

    /**
     * Returns whether this invitation is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this invitation has been sent.
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return ($this->status & self::STATUS_SENT) > 0;
    }

    /**
     * The account to which the invitation was used for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }
}
