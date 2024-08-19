<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait StatusPropertyTrait
{
    /**
     * Returns whether this object is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return defined('static::STATUS_ACTIVE') && ($this->status & static::STATUS_ACTIVE) > 0;
    }

    /**
     * Returns whether this object is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return defined('static::STATUS_DELETED') && ($this->status & static::STATUS_DELETED) > 0;
    }

    /**
     * Returns whether this object is registered in IMAP.
     *
     * @return bool
     */
    public function isImapReady(): bool
    {
        return defined('static::STATUS_IMAP_READY') && ($this->status & static::STATUS_IMAP_READY) > 0;
    }

    /**
     * Returns whether this object is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return defined('static::STATUS_LDAP_READY') && ($this->status & static::STATUS_LDAP_READY) > 0;
    }

    /**
     * Returns whether this object is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return defined('static::STATUS_NEW') && ($this->status & static::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this object is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return defined('static::STATUS_SUSPENDED') && ($this->status & static::STATUS_SUSPENDED) > 0;
    }

    /**
     * Returns object's statuses in a textual form
     */
    public function statusText(): string
    {
        $reflection = new \ReflectionClass(get_class($this));
        $result = [];

        foreach ($reflection->getConstants() as $const => $value) {
            if (str_starts_with($const, 'STATUS_') && ($this->status & $value) > 0) {
                $result[] = Str::camel(strtolower(str_replace('STATUS_', '', $const))) . " ($value)";
            }
        }

        return implode(', ', $result);
    }

    /**
     * Suspend this object.
     *
     * @return void
     */
    public function suspend(): void
    {
        if (!defined('static::STATUS_SUSPENDED') || $this->isSuspended()) {
            return;
        }

        $this->status |= static::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Unsuspend this object.
     *
     * @return void
     */
    public function unsuspend(): void
    {
        if (!defined('static::STATUS_SUSPENDED') || !$this->isSuspended()) {
            return;
        }

        $this->status ^= static::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Status property mutator
     *
     * @throws \Exception
     */
    public function setStatusAttribute($status)
    {
        if ($status & ~$this->allowed_states) {
            throw new \Exception("Invalid status: {$status}");
        }

        $this->attributes['status'] = $status;
    }
}
