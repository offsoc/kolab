<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\SharedFolderConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
use App\Traits\UuidIntKeyTrait;
use App\Wallet;
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
    use BelongsToTenantTrait;
    use EntitleableTrait;
    use SharedFolderConfigTrait;
    use SettingsTrait;
    use SoftDeletes;
    use StatusPropertyTrait;
    use UuidIntKeyTrait;

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

    /** @var array Mass-assignable properties */
    protected $fillable = [
        'email',
        'name',
        'status',
        'type',
    ];

    /** @var ?string Domain name for a shared folder to be created */
    public $domain;


    /**
     * Returns the shared folder domain.
     *
     * @return ?\App\Domain The domain to which the folder belongs to, NULL if it does not exist
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
     * Find whether an email address exists as a shared folder (including deleted folders).
     *
     * @param string $email         Email address
     * @param bool   $return_folder Return SharedFolder instance instead of boolean
     *
     * @return \App\SharedFolder|bool True or Resource model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_folder = false)
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $folder = self::withTrashed()->where('email', $email)->first();

        if ($folder) {
            return $return_folder ? $folder : true;
        }

        return false;
    }

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
