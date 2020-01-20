<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    // we've simply never heard of this domain
    public const STATUS_NEW        = 1 << 0;
    // it's been activated -- mutually exclusive with new?
    public const STATUS_ACTIVE     = 1 << 1;
    // ownership of the domain has been confirmed -- mutually exclusive with new?
    public const STATUS_CONFIRMED  = 1 << 2;
    // domain has been suspended.
    public const STATUS_SUSPENDED  = 1 << 3;
    // domain has been deleted -- can not be active any more.
    public const STATUS_DELETED    = 1 << 4;

    // open for public registration
    public const TYPE_PUBLIC       = 1 << 0;
    // zone hosted with us
    public const TYPE_HOSTED       = 1 << 1;
    // zone registered externally
    public const TYPE_EXTERNAL     = 1 << 2;

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
     * Return list of public+active domain names
     */
    public static function getPublicDomains(): array
    {
        $where = sprintf('(type & %s) AND (status & %s)', Domain::TYPE_PUBLIC, Domain::STATUS_ACTIVE);

        return self::whereRaw($where)->get(['namespace'])->map(function ($domain) {
            return $domain->namespace;
        })->toArray();
    }

    /**
     * Returns whether this domain is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status & self::STATUS_ACTIVE;
    }

    /**
     * Returns whether this domain is confirmed the ownership of.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->status & self::STATUS_CONFIRMED;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->status & self::STATUS_DELETED;
    }

    /**
     * Returns whether this domain is registered with us.
     *
     * @return bool
     */
    public function isExternal(): bool
    {
        return $this->type & self::TYPE_EXTERNAL;
    }

    /**
     * Returns whether this domain is hosted with us.
     *
     * @return bool
     */
    public function isHosted(): bool
    {
        return $this->type & self::TYPE_HOSTED;
    }

    /**
     * Returns whether this domain is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->status & self::STATUS_NEW;
    }

    /**
     * Returns whether this domain is public.
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->type & self::TYPE_PUBLIC;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status & self::STATUS_SUSPENDED;
    }

    /**
     * Domain status mutator
     *
     * @throws \Exception
     */
    public function setStatusAttribute($status)
    {
        $new_status = 0;

        $allowed_values = [
            self::STATUS_NEW,
            self::STATUS_ACTIVE,
            self::STATUS_CONFIRMED,
            self::STATUS_SUSPENDED,
            self::STATUS_DELETED,
        ];

        foreach ($allowed_values as $value) {
            if ($status & $value) {
                $new_status |= $value;
                $status ^= $value;
            }
        }

        if ($status > 0) {
            throw new \Exception("Invalid domain status: {$status}");
        }

        $this->attributes['status'] = $new_status;
    }
}
