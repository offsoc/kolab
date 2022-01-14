<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\GroupConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
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
 * @property string $name      The group name
 * @property int    $status    The group status
 * @property int    $tenant_id Tenant identifier
 */
class Group extends Model
{
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use GroupConfigTrait;
    use SettingsTrait;
    use SoftDeletes;
    use StatusPropertyTrait;
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
        'members',
        'name',
        'status',
    ];


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
}
