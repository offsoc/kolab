<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\EmailPropertyTrait;
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
    use EmailPropertyTrait;
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
