<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a Domain.
 *
 * @property string $namespace
 */
class Domain extends Model
{
    use SoftDeletes;

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

    public const HASH_CODE = 1;
    public const HASH_TEXT = 2;
    public const HASH_CNAME = 3;

    public $incrementing = false;

    protected $keyType = 'bigint';

    protected $fillable = [
        'namespace',
        'status',
        'type'
    ];

    /**
     * Assign a package to a domain. The domain should not belong to any existing entitlements.
     *
     * @param \App\Package   $package The package to assign.
     *
     * @return \App\Domain
     */
    public function assignPackage($package, $user)
    {
        $wallet_id = $user->wallets()->get()[0]->id;

        foreach ($package->skus as $sku) {
            for ($i = $sku->pivot->qty; $i > 0; $i--) {
                \App\Entitlement::create(
                    [
                        'owner_id' => $user->id,
                        'wallet_id' => $wallet_id,
                        'sku_id' => $sku->id,
                        'entitleable_id' => $this->id,
                        'entitleable_type' => Domain::class
                    ]
                );
            }
        }

        return $this;
    }


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
        return ($this->status & self::STATUS_ACTIVE) == true;
    }

    /**
     * Returns whether this domain is confirmed the ownership of.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return ($this->status & self::STATUS_CONFIRMED) == true;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->status & self::STATUS_DELETED) == true;
    }

    /**
     * Returns whether this domain is registered with us.
     *
     * @return bool
     */
    public function isExternal(): bool
    {
        return ($this->type & self::TYPE_EXTERNAL) == true;
    }

    /**
     * Returns whether this domain is hosted with us.
     *
     * @return bool
     */
    public function isHosted(): bool
    {
        return ($this->type & self::TYPE_HOSTED) == true;
    }

    /**
     * Returns whether this domain is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) == true;
    }

    /**
     * Returns whether this domain is public.
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        return ($this->type & self::TYPE_PUBLIC) == true;
    }

    /**
     * Returns whether this domain is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return ($this->status & self::STATUS_LDAP_READY) == true;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return ($this->status & self::STATUS_SUSPENDED) == true;
    }

    /**
     * Returns whether this (external) domain has been verified
     * to exist in DNS.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return ($this->status & self::STATUS_VERIFIED) == true;
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

        $hash = $this->hash(self::HASH_TEXT);
        $confirmed = false;

        // Get DNS records and find a matching TXT entry
        $records = \dns_get_record($this->namespace, DNS_TXT);

        if ($records === false) {
            throw new \Exception("Failed to get DNS record for {$this->namespace}");
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
            $cname = $this->hash(self::HASH_CODE) . '.' . $this->namespace;
            $records = \dns_get_record('kolab-verify.' . $this->namespace, DNS_CNAME);

            if ($records === false) {
                throw new \Exception("Failed to get DNS record for {$this->namespace}");
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
     * @param int $mod One of: HASH_CNAME, HASH_CODE (Default), HASH_TEXT
     *
     * @return string Verification hash
     */
    public function hash($mod = null): string
    {
        $cname = 'kolab-verify';

        if ($mod === self::HASH_CNAME) {
            return $cname;
        }

        $hash = \md5('hkccp-verify-' . $this->namespace);

        return $mod === self::HASH_TEXT ? "$cname=$hash" : $hash;
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

        $record = \dns_get_record($this->namespace, DNS_ANY);

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
