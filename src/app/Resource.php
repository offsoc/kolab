<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\EmailPropertyTrait;
use App\Traits\ResourceConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
use App\Traits\UuidIntKeyTrait;
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
    use StatusPropertyTrait;
    use UuidIntKeyTrait;
    use EmailPropertyTrait; // must be first after UuidIntKeyTrait

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

    // A template for the email attribute on a resource creation
    public const EMAIL_TEMPLATE = 'resource-{id}@{domainName}';

    /** @var int The allowed states for this object used in StatusPropertyTrait */
    private int $allowed_states = self::STATUS_NEW |
        self::STATUS_ACTIVE |
        self::STATUS_DELETED |
        self::STATUS_LDAP_READY |
        self::STATUS_IMAP_READY;

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['email', 'name', 'status'];
}
