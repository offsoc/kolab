<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of an Entitlement.
 *
 * Owned by a {@link \App\User}, billed to a {@link \App\Wallet}.
 */
class Entitlement extends Model
{
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
        'user_id',
        'wallet_id',
        'description'
    ];

    /**
     * The SKU concerned.
     *
     * @return Sku
     */
    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }

    /**
     * The owner of this entitlement.
     *
     * @return User
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'owner_id');
    }

    /**
     * The target user for this entitlement
     *
     * @return User
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * The wallet this entitlement is being billed to
     *
     * @return Wallet
     */
    public function wallet()
    {
        return $this->belongsTo('App\Wallet');
    }
}
