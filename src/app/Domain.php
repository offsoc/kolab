<?php

namespace App;

use App\Wallet;
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
     * @param \App\Package $package The package to assign.
     * @param \App\User    $user    The wallet owner.
     *
     * @return \App\Domain Self
     */
    public function assignPackage($package, $user)
    {
        // If this domain is public it can not be assigned to a user.
        if ($this->isPublic()) {
            return $this;
        }

        // See if this domain is already owned by another user.
        $wallet = $this->wallet();

        if ($wallet) {
            \Log::error(
                "Domain {$this->namespace} is already assigned to {$wallet->owner->email}"
            );

            return $this;
        }

        $wallet_id = $user->wallets()->first()->id;

        foreach ($package->skus as $sku) {
            for ($i = $sku->pivot->qty; $i > 0; $i--) {
                \App\Entitlement::create(
                    [
                        'wallet_id' => $wallet_id,
                        'sku_id' => $sku->id,
                        'cost' => $sku->pivot->cost(),
                        'entitleable_id' => $this->id,
                        'entitleable_type' => Domain::class
                    ]
                );
            }
        }

        return $this;
    }

    /**
     * The domain entitlement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function entitlement()
    {
        return $this->morphOne('App\Entitlement', 'entitleable');
    }

    /**
     * Return list of public+active domain names
     */
    public static function getPublicDomains(): array
    {
        $where = sprintf('(type & %s)', Domain::TYPE_PUBLIC);

        return self::whereRaw($where)->get(['namespace'])->pluck('namespace')->toArray();
    }

    /**
     * Returns whether this domain is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return ($this->status & self::STATUS_ACTIVE) > 0;
    }

    /**
     * Returns whether this domain is confirmed the ownership of.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return ($this->status & self::STATUS_CONFIRMED) > 0;
    }

    /**
     * Returns whether this domain is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->status & self::STATUS_DELETED) > 0;
    }

    /**
     * Returns whether this domain is registered with us.
     *
     * @return bool
     */
    public function isExternal(): bool
    {
        return ($this->type & self::TYPE_EXTERNAL) > 0;
    }

    /**
     * Returns whether this domain is hosted with us.
     *
     * @return bool
     */
    public function isHosted(): bool
    {
        return ($this->type & self::TYPE_HOSTED) > 0;
    }

    /**
     * Returns whether this domain is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this domain is public.
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        return ($this->type & self::TYPE_PUBLIC) > 0;
    }

    /**
     * Returns whether this domain is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return ($this->status & self::STATUS_LDAP_READY) > 0;
    }

    /**
     * Returns whether this domain is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return ($this->status & self::STATUS_SUSPENDED) > 0;
    }

    /**
     * Returns whether this (external) domain has been verified
     * to exist in DNS.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return ($this->status & self::STATUS_VERIFIED) > 0;
    }

    /**
     * Ensure the namespace is appropriately cased.
     */
    public function setNamespaceAttribute($namespace)
    {
        $this->attributes['namespace'] = strtolower($namespace);
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
            self::STATUS_SUSPENDED,
            self::STATUS_DELETED,
            self::STATUS_CONFIRMED,
            self::STATUS_VERIFIED,
            self::STATUS_LDAP_READY,
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

        if ($this->isPublic()) {
            $this->attributes['status'] = $new_status;
            return;
        }

        if ($new_status & self::STATUS_CONFIRMED) {
            // if we have confirmed ownership of or management access to the domain, then we have
            // also confirmed the domain exists in DNS.
            $new_status |= self::STATUS_VERIFIED;
            $new_status |= self::STATUS_ACTIVE;
        }

        if ($new_status & self::STATUS_DELETED && $new_status & self::STATUS_ACTIVE) {
            $new_status ^= self::STATUS_ACTIVE;
        }

        if ($new_status & self::STATUS_SUSPENDED && $new_status & self::STATUS_ACTIVE) {
            $new_status ^= self::STATUS_ACTIVE;
        }

        // if the domain is now active, it is not new anymore.
        if ($new_status & self::STATUS_ACTIVE && $new_status & self::STATUS_NEW) {
            $new_status ^= self::STATUS_NEW;
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
     * Suspend this domain.
     *
     * @return void
     */
    public function suspend(): void
    {
        if ($this->isSuspended()) {
            return;
        }

        $this->status |= Domain::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Unsuspend this domain.
     *
     * The domain is unsuspended through either of the following courses of actions;
     *
     *   * The account balance has been topped up, or
     *   * a suspected spammer has resolved their issues, or
     *   * the command-line is triggered.
     *
     * Therefore, we can also confidently set the domain status to 'active' should the ownership of or management
     * access to have been confirmed before.
     *
     * @return void
     */
    public function unsuspend(): void
    {
        if (!$this->isSuspended()) {
            return;
        }

        $this->status ^= Domain::STATUS_SUSPENDED;

        if ($this->isConfirmed() && $this->isVerified()) {
            $this->status |= Domain::STATUS_ACTIVE;
        }

        $this->save();
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

        $records = \dns_get_record($this->namespace, DNS_ANY);

        if ($records === false) {
            throw new \Exception("Failed to get DNS record for {$this->namespace}");
        }

        // It may happen that result contains other domains depending on the host DNS setup
        // that's why in_array() and not just !empty()
        if (in_array($this->namespace, array_column($records, 'host'))) {
            $this->status |= Domain::STATUS_VERIFIED;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Returns the wallet by which the domain is controlled
     *
     * @return \App\Wallet A wallet object
     */
    public function wallet(): ?Wallet
    {
        // Note: Not all domains have a entitlement/wallet
        $entitlement = $this->entitlement()->withTrashed()->orderBy('created_at', 'desc')->first();

        return $entitlement ? $entitlement->wallet : null;
    }
}
