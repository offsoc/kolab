<?php

namespace App;

use App\Traits\BelongsToTenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a License
 *
 * @property int    $id        The license identifier
 * @property string $key       The license key
 * @property string $type      An email address
 * @property int    $tenant_id Tenant identifier
 * @property ?int   $user_id   User identifier
 */
class License extends Model
{
    use BelongsToTenantTrait;

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['key', 'type', 'tenant_id'];
}
