<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Permission.
 *
 * @property string  $id               Permission identifier
 * @property int     $rights           Access rights
 * @property int     $permissible_id   The shared object identifier
 * @property string  $permissible_type The shared object type (class name)
 * @property string  $user             Permitted user (email)
 */
class Permission extends Model
{
    use UuidStrKeyTrait;

    public const READ  = 1;
    public const WRITE = 2;
    public const ADMIN = 4;


    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'permissible_id',
        'permissible_type',
        'rights',
        'user',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'rights' => 'integer',
    ];

    /**
     * Principally permissible object such as Room.
     * Note that it may be trashed (soft-deleted).
     *
     * @return mixed
     */
    public function permissible()
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * Rights mutator. Make sure rights is integer.
     */
    public function setRightsAttribute($rights): void
    {
        $this->attributes['rights'] = (int) $rights;
    }
}
