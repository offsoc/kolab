<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\UuidIntKeyTrait;
use App\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a Group.
 *
 * @property int    $id        The group identifier
 * @property string $email     An email address
 * @property string $members   A comma-separated list of email addresses
 * @property int    $status    The group status
 * @property int    $tenant_id Tenant identifier
 */
class Group extends Model
{
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use SoftDeletes;
    use UuidIntKeyTrait;

    // we've simply never heard of this group
    public const STATUS_NEW        = 1 << 0;
    // group has been activated
    public const STATUS_ACTIVE     = 1 << 1;
    // group has been suspended.
    public const STATUS_SUSPENDED  = 1 << 2;
    // group has been deleted
    public const STATUS_DELETED    = 1 << 3;
    // group has been created in LDAP
    public const STATUS_LDAP_READY = 1 << 4;

    protected $fillable = [
        'email',
        'status',
        'members'
    ];

    /**
     * Assign the group to a wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return \App\Group Self
     * @throws \Exception
     */
    public function assignToWallet(Wallet $wallet): Group
    {
        if (empty($this->id)) {
            throw new \Exception("Group not yet exists");
        }

        if ($this->entitlements()->count()) {
            throw new \Exception("Group already assigned to a wallet");
        }

        $sku = \App\Sku::withObjectTenantContext($this)->where('title', 'group')->first();
        $exists = $wallet->entitlements()->where('sku_id', $sku->id)->count();

        \App\Entitlement::create([
            'wallet_id' => $wallet->id,
            'sku_id' => $sku->id,
            'cost' => $exists >= $sku->units_free ? $sku->cost : 0,
            'fee' => $exists >= $sku->units_free ? $sku->fee : 0,
            'entitleable_id' => $this->id,
            'entitleable_type' => Group::class
        ]);

        return $this;
    }

    /**
     * Returns group domain.
     *
     * @return ?\App\Domain The domain group belongs to, NULL if it does not exist
     */
    public function domain(): ?Domain
    {
        list($local, $domainName) = explode('@', $this->email);

        return Domain::where('namespace', $domainName)->first();
    }

    /**
     * Find whether an email address exists as a group (including deleted groups).
     *
     * @param string $email        Email address
     * @param bool   $return_group Return Group instance instead of boolean
     *
     * @return \App\Group|bool True or Group model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_group = false)
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $group = self::withTrashed()->where('email', $email)->first();

        if ($group) {
            return $return_group ? $group : true;
        }

        return false;
    }

    /**
     * Group members propert accessor. Converts internal comma-separated list into an array
     *
     * @param string $members Comma-separated list of email addresses
     *
     * @return array Email addresses of the group members, as an array
     */
    public function getMembersAttribute($members): array
    {
        return $members ? explode(',', $members) : [];
    }

    /**
     * Returns whether this group is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return ($this->status & self::STATUS_ACTIVE) > 0;
    }

    /**
     * Returns whether this group is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return ($this->status & self::STATUS_DELETED) > 0;
    }

    /**
     * Returns whether this group is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return ($this->status & self::STATUS_NEW) > 0;
    }

    /**
     * Returns whether this group is registered in LDAP.
     *
     * @return bool
     */
    public function isLdapReady(): bool
    {
        return ($this->status & self::STATUS_LDAP_READY) > 0;
    }

    /**
     * Returns whether this group is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return ($this->status & self::STATUS_SUSPENDED) > 0;
    }

    /**
     * Ensure the email is appropriately cased.
     *
     * @param string $email Group email address
     */
    public function setEmailAttribute(string $email)
    {
        $this->attributes['email'] = strtolower($email);
    }

    /**
     * Ensure the members are appropriately formatted.
     *
     * @param array $members Email addresses of the group members
     */
    public function setMembersAttribute(array $members): void
    {
        $members = array_unique(array_filter(array_map('strtolower', $members)));

        sort($members);

        $this->attributes['members'] = implode(',', $members);
    }

    /**
     * Group status mutator
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
            self::STATUS_LDAP_READY,
        ];

        foreach ($allowed_values as $value) {
            if ($status & $value) {
                $new_status |= $value;
                $status ^= $value;
            }
        }

        if ($status > 0) {
            throw new \Exception("Invalid group status: {$status}");
        }

        $this->attributes['status'] = $new_status;
    }

    /**
     * Suspend this group.
     *
     * @return void
     */
    public function suspend(): void
    {
        if ($this->isSuspended()) {
            return;
        }

        $this->status |= Group::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Unsuspend this group.
     *
     * @return void
     */
    public function unsuspend(): void
    {
        if (!$this->isSuspended()) {
            return;
        }

        $this->status ^= Group::STATUS_SUSPENDED;
        $this->save();
    }
}
