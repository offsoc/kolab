<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    // we've simply never heard of this domain
    public const STATUS_NEW        = 1 << 0;
    // it's been activated
    public const STATUS_ACTIVE     = 1 << 1;
    // domain has been suspended.
    public const STATUS_SUSPENDED  = 1 << 2;
    // domain has been deleted
    public const STATUS_DELETED    = 1 << 3;
    // ownership of the domain has been confirmed
    public const STATUS_CONFIRMED  = 1 << 4;
    // domain has been verified that it exists in DNS
    public const STATUS_VERIFIED   = 1 << 5;
    // domain has been created in LDAP
    public const STATUS_LDAP_READY = 1 << 6;

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
     * Returns whether this domain is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return $this->status & self::STATUS_LDAP_READY;
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
     * Returns whether this (external) domain has been verified
     * to exist in DNS.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->status & self::STATUS_VERIFIED;
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
            self::STATUS_LDAP_READY,
            self::STATUS_VERIFIED,
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

    /**
     * Ownership verification by checking for a TXT (or CNAME) record
     * in the domain's DNS (that matches the verification hash).
     *
     * @return bool True if verification was successful, false otherwise
     * @throws \Exception Throws exception on DNS or DB errors
     */
    public function confirm(): bool
    {
        if ($this->isConfirmed()) {
            return true;
        }

        $hash = $this->hash();
        $confirmed = false;

        // Get DNS records and find a matching TXT entry
        $records = \dns_get_record($this->namespace, DNS_TXT);

        if ($records === false) {
            throw new \Exception("Failed to get DNS record for $domain");
        }

        foreach ($records as $record) {
            if ($record['txt'] === $hash) {
                $confirmed = true;
                break;
            }
        }

        // Get DNS records and find a matching CNAME entry
        // Note: some servers resolve every non-existing name
        // so we need to define left and right side of the CNAME record
        // i.e.: kolab-verify IN CNAME <hash>.domain.tld.
        if (!$confirmed) {
            $cname = $this->hash(true) . '.' . $this->namespace;
            $records = \dns_get_record('kolab-verify.' . $this->namespace, DNS_CNAME);

            if ($records === false) {
                throw new \Exception("Failed to get DNS record for $domain");
            }

            foreach ($records as $records) {
                if ($records['target'] === $cname) {
                    $confirmed = true;
                    break;
                }
            }
        }

        if ($confirmed) {
            $this->status |= Domain::STATUS_CONFIRMED;
            $this->save();
        }

        return $confirmed;
    }

    /**
     * Generate a verification hash for this domain
     *
     * @param bool $short Return short version (with kolab-verify= prefix)
     *
     * @return string Verification hash
     */
    public function hash($short = false): string
    {
        $hash = \md5('hkccp-verify-' . $this->namespace . $this->id);

        return $short ? $hash : "kolab-verify=$hash";
    }

    /**
     * Verify if a domain exists in DNS
     *
     * @return bool True if registered, False otherwise
     * @throws \Exception Throws exception on DNS or DB errors
     */
    public function verify(): bool
    {
        if ($this->isVerified()) {
            return true;
        }

        $record = \dns_get_record($this->namespace, DNS_SOA);

        if ($record === false) {
            throw new \Exception("Failed to get DNS record for {$this->namespace}");
        }

        if (!empty($record)) {
            $this->status |= Domain::STATUS_VERIFIED;
            $this->save();

            return true;
        }

        return false;
    }
}
