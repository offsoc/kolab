<?php

namespace App\Traits;

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
        $new_status = 0;

        $allowed_states = [
            'STATUS_NEW',
            'STATUS_ACTIVE',
            'STATUS_SUSPENDED',
            'STATUS_DELETED',
            'STATUS_LDAP_READY',
            'STATUS_IMAP_READY',
        ];

        foreach ($allowed_states as $const) {
            if (!defined("static::$const")) {
                continue;
            }

            $value = constant("static::$const");

            if ($status & $value) {
                $new_status |= $value;
                $status ^= $value;
            }
        }

        if ($status > 0) {
            throw new \Exception("Invalid status: {$status}");
        }

        $this->attributes['status'] = $new_status;
    }
}
