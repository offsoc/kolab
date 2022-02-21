<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use App\Traits\EntitleableTrait;
use App\Traits\ResourceConfigTrait;
use App\Traits\SettingsTrait;
use App\Traits\StatusPropertyTrait;
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
    use StatusPropertyTrait;
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

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = ['email', 'name', 'status'];

    /** @var ?string Domain name for a resource to be created */
    public $domain;


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
}
