<?php

namespace App;

use App\Traits\AliasesTrait;
use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\EmailPropertyTrait;
use App\Traits\SharedFolderConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
use App\Traits\UuidIntKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of a SharedFolder.
 *
 * @property string $email     An email address
 * @property int    $id        The folder identifier
 * @property string $name      The folder name
 * @property int    $status    The folder status
 * @property int    $tenant_id Tenant identifier
 * @property string $type      The folder type
 */
class SharedFolder extends Model
{
    use AliasesTrait;
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use SharedFolderConfigTrait;
    use SettingsTrait;
    use SoftDeletes;
    use StatusPropertyTrait;
    use UuidIntKeyTrait;
    use EmailPropertyTrait; // must be after UuidIntKeyTrait

    // we've simply never heard of this folder
    public const STATUS_NEW        = 1 << 0;
    // folder has been activated
    public const STATUS_ACTIVE     = 1 << 1;
    // folder has been suspended.
    // public const STATUS_SUSPENDED  = 1 << 2;
    // folder has been deleted
    public const STATUS_DELETED    = 1 << 3;
    // folder has been created in LDAP
    public const STATUS_LDAP_READY = 1 << 4;
    // folder has been created in IMAP
    public const STATUS_IMAP_READY = 1 << 8;

    /** @const array Supported folder type labels */
    public const SUPPORTED_TYPES = ['mail', 'event', 'contact', 'task', 'note', 'file'];

    /** @const string A template for the email attribute on a folder creation */
    public const EMAIL_TEMPLATE = '{type}-{id}@{domainName}';

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'email',
        'name',
        'status',
        'type',
    ];

    /**
     * Folder type mutator
     *
     * @throws \Exception
     */
    public function setTypeAttribute($type)
    {
        if (!in_array($type, self::SUPPORTED_TYPES)) {
            throw new \Exception("Invalid shared folder type: {$type}");
        }

        $this->attributes['type'] = $type;
    }
}
