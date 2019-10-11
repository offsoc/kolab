<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    // we've simply never heard of this domain
    const STATUS_NEW        = 1 << 0;
    // it's been activated -- mutually exclusive with new?
    const STATUS_ACTIVE     = 1 << 1;
    // ownership of the domain has been confirmed -- mutually exclusive with new?
    const STATUS_CONFIRMED  = 1 << 2;
    // domain has been suspended.
    const STATUS_SUSPENDED  = 1 << 3;
    // domain has been deleted -- can not be active any more.
    const STATUS_DELETED    = 1 << 4;

    // open for public registration
    const TYPE_PUBLIC       = 1 << 0;
    // zone hosted with us
    const TYPE_HOSTED       = 1 << 1;
    // zone registered externally
    const TYPE_EXTERNAL     = 1 << 2;

    public $incrementing = false;
    protected $keyType = 'bigint';

    protected $fillable = [
        'namespace',
        'status',
        'type'
    ];

    //protected $guarded = [
    //    "status"
    //];

    public function entitlement()
    {
        return $this->morphOne('App\Entitlement', 'entitleable');
    }

    /**
     * Returns whether this domain is active.
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->status & self::STATUS_ACTIVE;
    }

    /**
     * Returns whether this domain is confirmed the ownership of.
     *
     * @return boolean
     */
    public function isConfirmed()
    {
        return $this->status & self::STATUS_CONFIRMED;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->status & self::STATUS_DELETED;
    }

    /**
     * Returns whether this domain is registered with us.
     *
     * @return boolean
     */
    public function isExternal()
    {
        return $this->type & self::TYPE_EXTERNAL;
    }

    /**
     * Returns whether this domain is hosted with us.
     *
     * @return boolean
     */
    public function isHosted()
    {
        return $this->type & self::TYPE_HOSTED;
    }

    /**
     * Returns whether this domain is new.
     *
     * @return boolean
     */
    public function isNew()
    {
        return $this->status & self::STATUS_NEW;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return boolean
     */
    public function isSuspended()
    {
        return $this->status & self::STATUS_SUSPENDED;
    }

    /*
    public function setStatusAttribute($status)
    {
        $_status = $this->status;

        switch ($status) {
            case "new":
                $_status += self::STATUS_NEW;
                break;
            case "active":
                $_status += self::STATUS_ACTIVE;
                $_status -= self::STATUS_NEW;
                break;
            case "confirmed":
                $_status += self::STATUS_CONFIRMED;
                $_status -= self::STATUS_NEW;
                break;
            case "suspended":
                $_status += self::STATUS_SUSPENDED;
                break;
            case "deleted":
                $_status += self::STATUS_DELETED;
                break;
            default:
                $_status = $status;
                //throw new \Exception("Invalid domain status: {$status}");
                break;
        }

        $this->status = $_status;
    }
    */
}
