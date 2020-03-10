<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of an Entitlement.
 *
 * Owned by a {@link \App\User}, billed to a {@link \App\Wallet}.
 *
 * @property \App\User $owner                   The owner of this entitlement (subject).
 * @property \App\Sku $sku                      The SKU to which this entitlement applies.
 * @property \App\Wallet $wallet                The wallet to which this entitlement is charged.
 * @property \App\Domain|\App\User $entitleable The entitled object (receiver of the entitlement).
 */
class Entitlement extends Model
{
    use SoftDeletes;

    /**
     * This table does not use auto-increment.
     *
     * @var boolean
     */
    public $incrementing = false;

    /**
     * The key type is actually a string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The fillable columns for this Entitlement
     *
     * @var array
     */
    protected $fillable = [
        'sku_id',
        'owner_id',
        'wallet_id',
        'entitleable_id',
        'entitleable_type',
        'cost',
        'description'
    ];

    protected $casts = [
        'cost' => 'integer',
    ];

    /**
     * Principally entitleable objects such as 'Domain' or 'User'.
     *
     * @return mixed
     */
    public function entitleable()
    {
        return $this->morphTo();
    }

    /**
     * The SKU concerned.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }

    /**
     * The owner of this entitlement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'owner_id', 'id');
    }

    /**
     * The wallet this entitlement is being billed to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo('App\Wallet');
    }

    /**
     * Cost mutator. Make sure cost is integer.
     */
    public function setCostAttribute($cost): void
    {
        $this->attributes['cost'] = round($cost);
    }
}
