<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\ResourceConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\UuidIntKeyTrait;
use App\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a Resource.
 *
 * @property int    $id        The resource identifier
 * @property string $email     An email address
 * @property string $name      The resource name
 * @property int    $status    The resource status
 * @property int    $tenant_id Tenant identifier
 */
class Resource extends Model
{
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use ResourceConfigTrait;
    use SettingsTrait;
    use SoftDeletes;
    use UuidIntKeyTrait;

    // we've simply never heard of this resource
    public const STATUS_NEW        = 1 << 0;
    // resource has been activated
    public const STATUS_ACTIVE     = 1 << 1;
    // resource has been suspended.
    // public const STATUS_SUSPENDED  = 1 << 2;
    // resource has been deleted
    public const STATUS_DELETED    = 1 << 3;
    // resource has been created in LDAP
    public const STATUS_LDAP_READY = 1 << 4;
    // resource has been created in IMAP
    public const STATUS_IMAP_READY = 1 << 8;

    protected $fillable = [
        'email',
        'name',
        'status',
    ];

    /**
     * @var ?string Domain name for a resource to be created */
    public $domain;


    /**
     * Assign the resource to a wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return \App\Resource Self
     * @throws \Exception
     */
    public function assignToWallet(Wallet $wallet): Resource
    {
        if (empty($this->id)) {
            throw new \Exception("Resource not yet exists");
        }

        if ($this->entitlements()->count()) {
            throw new \Exception("Resource already assigned to a wallet");
        }

        $sku = \App\Sku::withObjectTenantContext($this)->where('title', 'resource')->first();
        $exists = $wallet->entitlements()->where('sku_id', $sku->id)->count();

        \App\Entitlement::create([
            'wallet_id' => $wallet->id,
            'sku_id' => $sku->id,
            'cost' => $exists >= $sku->units_free ? $sku->cost : 0,
            'fee' => $exists >= $sku->units_free ? $sku->fee : 0,
            'entitleable_id' => $this->id,
            'entitleable_type' => Resource::class
        ]);

        return $this;
    }

    /**
     * Returns the resource domain.
     *
     * @return ?\App\Domain The domain to which the resource belongs to, NULL if it does not exist
     */
    public function domain(): ?Domain
    {
        if (isset($this->domain)) {
            $domainName = $this->domain;
        } else {
            list($local, $domainName) = explode('@', $this->email);
        }

        return Domain::where('namespace', $domainName)->first();
    }

    /**
     * Find whether an email address exists as a resource (including deleted resources).
     *
     * @param string $email           Email address
     * @param bool   $return_resource Return Resource instance instead of boolean
     *
     * @return \App\Resource|bool True or Resource model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_resource = false)
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $resource = self::withTrashed()->where('email', $email)->first();

        if ($resource) {
            return $return_resource ? $resource : true;
        }

        return false;
    }

    /**
     * Returns whether this resource is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return ($this->status & self::STATUS_ACTIVE) > 0;
    }

    /**
     * Returns whether this resource is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->status & self::STATUS_DELETED) > 0;
    }

    /**
     * Returns whether this resource's folder exists in IMAP.
     *
     * @return bool
     */
    public function isImapReady(): bool
    {
        return ($this->status & self::STATUS_IMAP_READY) > 0;
    }

    /**
     * Returns whether this resource is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return ($this->status & self::STATUS_LDAP_READY) > 0;
    }

    /**
     * Returns whether this resource is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Resource status mutator
     *
     * @throws \Exception
     */
    public function setStatusAttribute($status)
    {
        $new_status = 0;

        $allowed_values = [
            self::STATUS_NEW,
            self::STATUS_ACTIVE,
            self::STATUS_DELETED,
            self::STATUS_IMAP_READY,
            self::STATUS_LDAP_READY,
        ];

        foreach ($allowed_values as $value) {
            if ($status & $value) {
                $new_status |= $value;
                $status ^= $value;
            }
        }

        if ($status > 0) {
            throw new \Exception("Invalid resource status: {$status}");
        }

        $this->attributes['status'] = $new_status;
    }
}
